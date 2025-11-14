<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPasswordOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserTag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken; // if using Sanctum (optional)


class UserController extends Controller
{



    public function storeSessionData(Request $request)
    {
        $sessionId = $request->header('X-Session-Id'); // 🔥 Get session ID

        // Validate request input
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'ip_address' => 'required|string',
            'user_agent' => 'required|string',
            'payload' => 'required|string',
            'last_activity' => 'nullable|string',
        ]);

        // If no session ID, generate a new one (optional logic)
        if (!$sessionId) {
            $sessionId = Str::random(40); // generate random session id
        }

        // Check if session exists
        $existingSession = DB::table('sessions')->where('id', $sessionId)->first();

        if ($existingSession) {
            // 🔄 Update existing session
            DB::table('sessions')
                ->where('id', $sessionId)
                ->update([
                    'user_id' => $validated['user_id'],
                    'ip_address' => $validated['ip_address'],
                    'user_agent' => $validated['user_agent'],
                    'payload' => $validated['payload'],
                    'last_activity' => time(),
                ]);

            $message = 'Session updated successfully';
        } else {
            // 🆕 Create new session record
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $validated['user_id'],
                'ip_address' => $validated['ip_address'],
                'user_agent' => $validated['user_agent'],
                'payload' => $validated['payload'],
                'last_activity' => time(),
            ]);

            $message = 'New session created successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'session_id' => $sessionId,
        ]);
    }


    /**
     * Handle user login with session authentication
     */
    public function login(Request $request)
    {
        // Step 1: Validate input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Step 2: Attempt login
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }
        if (method_exists($user, 'markEmailAsVerified') && !$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        // Step 3: Log the user in (creates session)
        // if request contains a 'remember' boolean, pass it to Auth::login()
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate(); // important
        // Step 4: Regenerate session to prevent fixation attacks

        // Ensure default user tags exist
        try {
            $hasTags = UserTag::where('user_id', $user->id)->exists();
            if (! $hasTags) {
                $defaultTags = ['Work', 'Research', 'Education', 'AI', 'Reading'];
                $now = now();
                $toInsert = array_map(function ($tag) use ($user, $now) {
                    return [
                        'user_id' => $user->id,
                        'tag' => $tag,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $defaultTags);
                UserTag::insert($toInsert);
            }
        } catch (\Throwable $e) {
            Log::warning('Default tags creation skipped', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
        ]);
    }


    public function fetchUser(Request $request)
    {
        $user = $request->user();
        return response()->json($user);
    }
    public function updateName(Request $request)
    {
        // ✅ Validate incoming data
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:50',
        ]);

        // ✅ Find user by ID
        $ifuser = $request->user();
        if (!$ifuser) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        // ✅ Get the user ID from the authenticated user
        $id = $ifuser->id;

        $user = User::find($id);

        // Handle not found
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // ✅ Update the name
        $user->name = $validated['name'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User name updated successfully',
            'user' => $user,
        ], 200);
    }

    public function updateEmail(Request $request)
    {
        // ✅ Validate incoming data
        $validated = $request->validate([
            'email' => 'required|string|min:2|max:50',
        ]);

        // ✅ Find user by ID
        $ifuser = $request->user();
        if (!$ifuser) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        // ✅ Get the user ID from the authenticated user
        $id = $ifuser->id;

        $user = User::find($id);

        // Handle not found
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // ✅ Update the name
        $user->email = $validated['email'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User name updated successfully',
            'user' => $user,
        ], 200);
    }
    /**
     * Logout the authenticated user
     */
    public function logout(Request $request)
    {
        try {
            // Get user before logout
            $user = $request->user();
            $userId = $user ? $user->id : null;
            $sessionId = $request->session()->getId();

            // Revoke all Sanctum tokens for this user
            if ($user) {
                // Revoke current token if bearer token is present
                if ($request->bearerToken()) {
                    $token = $user->currentAccessToken();
                    if ($token) {
                        $token->delete();
                    }
                }

                // Revoke ALL tokens for this user (optional - uncomment if you want to logout from all devices)
                // PersonalAccessToken::where('tokenable_id', $userId)
                //     ->where('tokenable_type', get_class($user))
                //     ->delete();
            }

            // Delete session from database
            if ($sessionId) {
                DB::table('sessions')->where('id', $sessionId)->delete();
            }

            // Also delete all sessions for this user (optional - uncomment if you want to logout from all devices)
            // if ($userId) {
            //     DB::table('sessions')->where('user_id', $userId)->delete();
            // }

            // Logout from Auth guards
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            } elseif (Auth::check()) {
                Auth::logout();
            }

            // Invalidate and regenerate session
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            // Prepare response with cookie clearing
            $response = response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);

            // Clear all session-related cookies
            $sessionCookieName = config('session.cookie', 'laravel_session');
            $cookiesToForget = [
                $sessionCookieName,
                'XSRF-TOKEN',
                'laravel_session',
            ];

            foreach ($cookiesToForget as $cookieName) {
                if ($cookieName) {
                    // Create expired cookie to clear it
                    $response = $response->withCookie(
                        cookie(
                            $cookieName,
                            '',
                            -2628000, // Expire in the past (1 month ago)
                            '/',
                            config('session.domain'),
                            config('session.secure', false),
                            true, // httpOnly
                            false, // raw
                            config('session.same_site', 'lax')
                        )
                    );
                }
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('Logout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            // Still try to clear cookies even on error
            $response = response()->json([
                'success' => true,
                'message' => 'Logged out (with warnings)',
            ], 200);

            $sessionCookieName = config('session.cookie', 'laravel_session');
            $cookiesToForget = [$sessionCookieName, 'XSRF-TOKEN', 'laravel_session'];

            foreach ($cookiesToForget as $cookieName) {
                if ($cookieName) {
                    $response = $response->withCookie(
                        cookie($cookieName, '', -2628000, '/', config('session.domain'), config('session.secure', false), true, false, config('session.same_site', 'lax'))
                    );
                }
            }

            return $response;
        }
    }
    public function sendForgotPasswordOtp(Request $request)
    {
        // ✅ Validate email only
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // ✅ Check if user exists
        $user = \App\Models\User::where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No user found with this email address.',
            ], 404);
        }

        // ✅ Generate OTP
        $otp = random_int(100000, 999999);

        // ✅ Create a unique token key
        $otpToken = Str::random(40);
        $key = 'otp:' . $otpToken;

        // ✅ Prepare OTP data (10-minute expiry)
        $otpData = [
            'email' => $email,
            'code' => (string) $otp,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'expires_at_timestamp' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
            'max_attempts' => 5,
        ];

        // ✅ Store OTP details in cache (Redis recommended)
        Cache::put($key, $otpData, now()->addMinutes(10));

        // ✅ Send OTP via email (direct send, working version)
        try {
            Mail::to($email)->send(new \App\Mail\ForgotPasswordOtpMail($otp));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
                'error' => $e->getMessage(), // optional for debugging
            ], 500);
        }

        // ✅ Return success response
        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'otp_token' => $otpToken,
        ]);
    }


    public function verifyForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'otp_token' => 'required|string',
            'otp' => 'required|string',
        ]);

        $token = $request->input('otp_token');
        $inputOtp = (string) $request->input('otp');

        $cacheKey = 'otp:' . $token;
        $otpData = Cache::get($cacheKey);

        if (!$otpData) {
            return response()->json(['message' => 'Invalid or expired OTP token'], 400);
        }

        $otpData['attempts'] = $otpData['attempts'] ?? 0;
        $otpData['max_attempts'] = $otpData['max_attempts'] ?? 5;

        if ($otpData['attempts'] >= $otpData['max_attempts']) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Too many attempts. OTP invalidated.'], 429);
        }

        if (! hash_equals((string)$otpData['code'], $inputOtp)) {
            $otpData['attempts']++;
            $expiresAt = isset($otpData['expires_at'])
                ? \Illuminate\Support\Carbon::parse($otpData['expires_at'])
                : now()->addMinutes(10);

            Cache::put($cacheKey, $otpData, $expiresAt);
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        // ✅ Success: consume OTP
        Cache::forget($cacheKey);

        $verifiedEmail = $otpData['email'] ?? null;
        if (! $verifiedEmail) {
            return response()->json(['message' => 'Server error: missing email'], 500);
        }

        $user = \App\Models\User::where('email', $verifiedEmail)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // ✅ Create and store reset_token (15-minute expiry)
        $resetToken = Str::random(60);
        Cache::put("password_reset_token:{$resetToken}", [
            'user_id' => $user->id,
            'email' => $user->email,
        ], now()->addMinutes(15));

        // ✅ Return reset_token to frontend
        return response()->json([
            'message' => 'OTP verified successfully',
            'reset_token' => $resetToken, // <-- this is what frontend uses
            'expires_in' => 900, // seconds (15 minutes)
        ]);
    }

    public function resetPassword(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'reset_token' => 'required|string',
            'new_password' => ['required'], // expects new_password_confirmation
            // 'new_password' => ['required','string','min:8','max:72','confirmed'], // expects new_password_confirmation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $resetToken = $request->input('reset_token');
        $cacheKey = "password_reset_token:{$resetToken}";
        $payload = Cache::get($cacheKey);

        if (! $payload || ! isset($payload['user_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $user = User::find($payload['user_id']);
        if (! $user) {
            // Defensive: clear token if orphaned
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => 'User not found for this reset token.',
            ], 404);
        }

        $newPassword = $request->input('new_password');

        try {
            DB::transaction(function () use ($user, $newPassword, $cacheKey) {
                // Update password (hashed)
                $user->password = Hash::make($newPassword);

                // Optionally update password changed timestamp
                if (in_array('password_updated_at', array_keys($user->getAttributes()))) {
                    $user->password_updated_at = now();
                }

                // If you want to mark email verified here, do it outside reset flow
                $user->save();

                // Invalidate the reset token (single-use)
                Cache::forget($cacheKey);

                // OPTIONAL: revoke all Sanctum tokens (if using Sanctum)
                if (class_exists(PersonalAccessToken::class)) {
                    PersonalAccessToken::where('tokenable_id', $user->id)
                        ->where('tokenable_type', get_class($user))
                        ->delete();
                }

                // OPTIONAL: revoke other sessions (if you track them)
                // e.g. DB::table('sessions')->where('user_id', $user->id)->delete();
            });
        } catch (\Throwable $e) {
            Log::error('resetPassword failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Try again later.',
            ], 500);
        }

        // OPTIONAL: send a confirmation email to the user about the password change
        // Mail::to($user->email)->queue(new PasswordChangedNotification($user));

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please sign in with your new password.',
        ], 200);
    }
}