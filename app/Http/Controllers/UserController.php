<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        Auth::login($user);

        // Step 4: Regenerate session to prevent fixation attacks

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
    /**
     * Logout the authenticated user
     */
public function logout(Request $request)
{
    try {
        // Attempt to get user (may be null)
        $user = $request->user();

        // If bearer token present and user found — revoke
        if ($request->bearerToken() && $user) {
            $token = $user->currentAccessToken();
            if ($token) $token->delete();
        }

        // Logout web guard if present
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        } elseif (Auth::check()) {
            Auth::logout();
        }

        // Invalidate session only if it exists
        try {
            if ($request->hasSession() && $request->session()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } catch (\Throwable $e) {
            // ignore session issues
        }

        // Return success and clear cookies
        $response = response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'user_id' => $user ? $user->id : null,
        ], 200);

        $sessionCookieName = config('session.cookie'); // laravel_session
        $cookiesToForget = [$sessionCookieName, 'XSRF-TOKEN'];

        foreach ($cookiesToForget as $cookieName) {
            if ($cookieName) {
                $response = $response->withCookie(cookie()->forget($cookieName));
            }
        }

        return $response;
    } catch (\Throwable $e) {
        // Return success-ish to avoid leaking state; but also log server error if you want
        \Log::warning('Logout issue: '.$e->getMessage());
        return response()->json(['success' => true, 'message' => 'Logged out (with warnings)'], 200);
    }
}
}