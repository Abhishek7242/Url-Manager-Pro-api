<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Models\Url;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserTag;
use Illuminate\Support\Facades\Auth;

class OtpController extends Controller
{
    //


    public function sendOtp(Request $request)
    {
        // validate inputs (name and password required for signup)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        // ✅ Check if user already exists
        $existingUser = \App\Models\User::where('email', $email)->first();
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists with this email',
            ], 409); // 409 Conflict is semantically correct
        }

        // ✅ Generate OTP
        $otp = random_int(100000, 999999);

        // Create a random reference token
        $otpToken = Str::random(40);
        $key = 'otp:' . $otpToken;

        // Hash password before storing anywhere
        $passwordHash = Hash::make($password);

        $otpData = [
            'name' => $name,
            'email' => $email,
            'code' => (string) $otp,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'expires_at_timestamp' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
            'max_attempts' => 5,
            'password_hash' => $passwordHash,
        ];

        // Store in cache (Redis recommended)
        Cache::put($key, $otpData, now()->addMinutes(10));

        // Also store name and hashed password in session as requested
        session()->put('pending_user', [
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'otp_token' => $otpToken,
        ]);

        // Send OTP via mail
        Mail::to($email)->send(new SendOtpMail($otp)); // consider queueing in production

        // ✅ Return response
        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'otp_token' => $otpToken,
        ]);
    }



    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_token' => 'required|string',
            'otp' => 'required|string',
            // 'email' => 'sometimes|email' // optional client email, not trusted
        ]);

        $token = $request->input('otp_token');
        $inputOtp = (string) $request->input('otp');

        $cacheKey = 'otp:' . $token;
        $otpData = Cache::get($cacheKey);

        if (!$otpData) {
            return response()->json(['message' => 'Invalid or expired OTP token'], 400);
        }

        // Optional: sanity check if frontend provided email and it doesn't match
        // if ($request->filled('email') && $request->input('email') !== $otpData['email']) {
        //     // Option A: reject — stricter
        //     return response()->json(['message' => 'Mismatched email for OTP token'], 400);

        //     // Option B: ignore the client-supplied email and continue (safer if you expect client to not send email)
        //     // (do nothing here)
        // }

        // Attempts guard (ensure otpData has attempts and max_attempts)
        $otpData['attempts'] = $otpData['attempts'] ?? 0;
        $otpData['max_attempts'] = $otpData['max_attempts'] ?? 5;

        if ($otpData['attempts'] >= $otpData['max_attempts']) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Too many attempts. OTP invalidated.'], 429);
        }

        // Compare codes safely. If you stored a plain code, use hash_equals to avoid timing attacks.
        // If you stored hash, use Hash::check($inputOtp, $otpData['code_hash'])
        if (!hash_equals((string)$otpData['code'], $inputOtp)) {
            $otpData['attempts']++;
            // Keep original ttl: re-put with same expiry time if you stored expires_at
            $expiresAt = isset($otpData['expires_at']) ? \Illuminate\Support\Carbon::parse($otpData['expires_at']) : now()->addMinutes(10);
            Cache::put($cacheKey, $otpData, $expiresAt);
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        // SUCCESS: use server-stored email/name/password hash — prefer cache first
        $verifiedEmail = $otpData['email'];
        $verifiedName = $otpData['name'] ?? null;
        $passwordHash = $otpData['password_hash'] ?? null;

        // If cache didn't contain name/password_hash for some reason, fall back to session
        $pending = session('pending_user');
        if ((!$verifiedName || !$passwordHash) && $pending && isset($pending['otp_token']) && $pending['otp_token'] === $token) {
            $verifiedName = $verifiedName ?: ($pending['name'] ?? null);
            $passwordHash = $passwordHash ?: ($pending['password_hash'] ?? null);
        }

        // Invalidate token immediately (single-use)
        Cache::forget($cacheKey);
        // Clear pending session
        session()->forget('pending_user');

        if (!$passwordHash) {
            // Shouldn't happen, but handle gracefully
            return response()->json(['message' => 'Server error: missing password data'], 500);
        }

        // Create or update user with provided name and hashed password
        $user = User::firstOrCreate(
            ['email' => $verifiedEmail],
            ['name' => $verifiedName, 'password' => $passwordHash]
        );

        // If user existed and we want to ensure password/hash is set, update it
        if ($user->wasRecentlyCreated === false) {
            $user->name = $verifiedName ?: $user->name;
            $user->password = $passwordHash;
            $user->save();
        }

        // Mark email verified if applicable
        if (method_exists($user, 'markEmailAsVerified')) {
            $user->markEmailAsVerified();
        } else {
            $user->email_verified_at = now();
            $user->save();
        }
        // ------------------ Option B: create default user tags ------------------
        $defaultTags = ['Work', 'Research', 'Education', 'AI', 'Reading'];

        $toInsert = [];
        $now = now();

        foreach ($defaultTags as $tag) {
            $toInsert[] = [
                'user_id' => $user->id,
                'tag' => $tag,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert all 5 tags for the user
        UserTag::insert($toInsert);
        // Log the user in using session-based auth (Sanctum SPA)
        Auth::login($user);
        // Regenerate session ID to prevent fixation and ensure new cookie is sent
        $request->session()->regenerate();

        $sessionId = request()->input('session_id');
        $urls = Url::where('session_id', $sessionId)->get();
        \Illuminate\Support\Facades\DB::transaction(function () use ($urls, $user) {
            foreach ($urls as $url) {
                $url->user_id = $user->id;
                $url->session_id = null;
                $url->save(); // triggers model events
            }
        });

        // Return safe user info (omit sensitive fields)
        $safeUser = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'session_id' => request()->input('session_id'),
        ];

        return response()->json([
            'message' => 'OTP verified successfully',
            'user' => $safeUser,
        ]);
    }
}