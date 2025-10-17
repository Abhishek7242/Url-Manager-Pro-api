<?php

namespace App\Http\Controllers;

use App\Models\Url;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UrlController extends Controller
{
    /**
     * Get session ID from multiple sources
     */
    private function getSessionId(Request $request)
    {
        // Try to get from request input first
        $sessionId = $request->input('session_id');
        if ($sessionId) {
            return $sessionId;
        }

        // Try to get from header
        $sessionId = $request->header('X-Session-Id');
        if ($sessionId) {
            return $sessionId;
        }

        // Try to get from Laravel session (only if session is available)
        try {
            $sessionId = $request->session()->getId();
            if ($sessionId) {
                return $sessionId;
            }
        } catch (\Exception $e) {
            // Session not available, continue with other methods
        }

        return null;
    }

    /**
     * Ensure user has access to the URL (either authenticated or has valid session)
     */
    private function ensureUserAccess(Request $request)
    {
        $user = $request->user();
        if ($user) {
            return ['user_id' => $user->id, 'session_id' => $this->getSessionId($request)];
        }

        $sessionId = $this->getSessionId($request);
        if (!$sessionId) {
            return null;
        }

        return ['session_id' => $sessionId];
    }
    public function store(Request $request)
    {
        $session_id = $request->query('session_id') ;
        $authUser = $request->user();

        // ✅ Validate input
        $validated = $request->validate([
            'title'       => 'nullable|string|max:255',
            'url'         => 'required|url',
            'description' => 'nullable|string',
            'tags'        => 'nullable|array',
            'status'      => 'nullable|in:active,archived,deleted',
            'url_clicks'  => 'nullable|integer|min:0',
            'reminder_at' => 'nullable|date',
        ]);

        // ✅ Base data
        $data = $validated;
        if(!$authUser) {       
            $data['session_id'] = $session_id;
        }
        $data['status'] = $data['status'] ?? 'active';
        $data['url_clicks'] = $data['url_clicks'] ?? 0;

        // ✅ Normalize tags
        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            $data['tags'] = is_array($decoded) ? $decoded : [$data['tags']];
        }

        // ✅ Attach owner info
        $userAccess = $this->ensureUserAccess($request);
        if (!$userAccess) {
            return response()->json([
                'message' => 'session_id is required when unauthenticated',
            ], 422);
        }

        // If authenticated user exists → add user_id explicitly
        if ($authUser) {
            $data['user_id'] = $authUser->id;
        }

        // Merge access data (session_id or both)
        $data = array_merge($data, $userAccess);

        // ✅ Create the URL record
        $url = Url::create($data);

        return response()->json([
            'message' => 'URL stored successfully',
            'data'    => $url,
            'user_id' => $authUser ? $authUser->id : null,
        ], 201);
    }

    public function edit(Request $request, $id)
    {
        $session_id = $request->query('session_id');
        $authUser = $request->user();

        // Try to find the record either by user or session
        if ($authUser) {
            // Authenticated user — find by ID and user_id
            $url = Url::where('id', $id)
                ->where('user_id', $authUser->id)
                ->first();
        } else {
            // Guest user — find by ID and session_id
            $url = Url::where('id', $id)
                ->where('session_id', $session_id)
                ->first();
        }

        // If no record found
        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'URL not found',
            ], 404);
        }

        // Validate inputs
        $validated = $request->validate([
            'title'       => 'nullable|string|max:255',
            'url'         => 'nullable|url',
            'description' => 'nullable|string',
            'tags'        => 'nullable|array',
            'status'      => 'nullable|in:active,archived,deleted',
            'url_clicks'  => 'nullable|integer|min:0',
            'reminder_at' => 'nullable|date',
        ]);

        $data = $request->all();

        // Convert tags from JSON string to array if needed
        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            $data['tags'] = is_array($decoded) ? $decoded : [$data['tags']];
        }

        try {
            $url->update($data);

            return response()->json([
                'success' => true,
                'message' => 'URL updated successfully',
                'data'    => $url,
            ]);
        } catch (\Exception $e) {
            // Optionally log the error

            return response()->json([
                'success' => false,
                'message' => 'Could not update URL',
            ], 500);
        }
    }

    public function getAll(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $tag = $request->query('tag');

        $query = \App\Models\Url::query();

        // Scope by authenticated user or fallback to session_id
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('session_id')) {
            $query->where('session_id', $request->query('session_id'));
        }

        // Apply optional filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($tag) {
            // when tags stored as JSON array
            $query->whereJsonContains('tags', $tag);
        }

        $urls = $query->orderBy('created_at', 'desc')->get();

        // Format timestamps
        $urls->transform(function ($url) {
            $url->formatted_created_at = $url->created_at
                ? $url->created_at->format('M d, Y h:i A')
                : null;
            $url->formatted_updated_at = $url->updated_at
                ? $url->updated_at->format('M d, Y')
                : null;
            return $url;
        });

        // Build response consistently and safely
        $response = [
            'success'    => true,
            'count'      => $urls->count(),
            'data'       => $urls,
            'user_id'    => $user ? $user->id : null,
            'session_id' => $request->query('session_id') ?? null,
        ];

        if ($user) {
            // include useful user info (avoid accessing ->id when $user might be null)
            $response['user'] = [
                'id'   => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ];
        } else {
            $response['session_id'] = $request->query('session_id') ?? null;
        }

        return response()->json($response, 200);
    }
    public function getById(Request $request, $id)
    {
        $user = $request->user();

        $query = \App\Models\Url::query();

        // Scope by authenticated user or session_id
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('session_id')) {
            $query->where('session_id', $request->query('session_id'));
        }

        // Find the record by ID within user's/session's scope
        $url = $query->where('id', $id)->first();

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'URL not found or access denied.',
            ], 404);
        }

        // Format timestamps
        $url->formatted_created_at = $url->created_at
            ? $url->created_at->format('M d, Y h:i A')
            : null;

        $url->formatted_updated_at = $url->updated_at
            ? $url->updated_at->format('M d, Y')
            : null;

      

     

        return response()->json($url, 200);
    }


    /**
     * Get current session information for debugging
     */
    public function getSessionInfo(Request $request)
    {
        $user = $request->user();
        $sessionId = $this->getSessionId($request);

        $sessionData = [];
        try {
            $sessionData = $request->session()->all();
        } catch (\Exception $e) {
            $sessionData = ['error' => 'Session not available'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user ? $user->id : null,
                'session_id' => $sessionId,
                'is_authenticated' => $user ? true : false,
                'session_data' => $sessionData,
                'request_headers' => [
                    'x-session-id' => $request->header('X-Session-Id'),
                    'cookie' => $request->header('Cookie'),
                ]
            ]
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $session_id = $request->query('session_id');
        $authUser = $request->user();

        // Find the record either by user or session
        if ($authUser) {
            // Logged-in user
            $url = Url::where('id', $id)
                ->where('user_id', $authUser->id)
                ->first();
        } else {
            // Guest user (identified by session_id)
            $url = Url::where('id', $id)
                ->where('session_id', $session_id)
                ->first();
        }

        // If not found, return 404
        if (! $url) {
            return response()->json([
                'success' => false,
                'message' => 'URL not found',
            ], 404);
        }

        try {
            $url->delete();

            return response()->json([
                'success' => true,
                'message' => 'URL deleted successfully',
                'id' => $id,
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging

            return response()->json([
                'success' => false,
                'message' => 'Could not delete URL',
            ], 500);
        }
    }

    public function updateClickCount(Request $request, $id)
    {
        $session_id = $request->query('session_id');
        $authUser = $request->user();

        // 🔍 Find URL either by user or session
        if ($authUser) {
            $url = Url::where('id', $id)
                ->where('user_id', $authUser->id)
                ->first();
        } else {
            $url = Url::where('id', $id)
                ->where('session_id', $session_id)
                ->first();
        }

        // ❌ If not found
        if (! $url) {
            return response()->json([
                'success' => false,
                'message' => 'URL not found',
            ], 404);
        }

        try {
            // ✅ Increment the click count safely
            $url->increment('url_clicks');

            return response()->json([
                'success' => true,
                'message' => 'Click count updated successfully',
                'data' => [
                    'id' => $url->id,
                    'url_clicks' => $url->url_clicks,
                ],
            ]);
        } catch (\Exception $e) {
            // Log error for debugging

            return response()->json([
                'success' => false,
                'message' => 'Failed to update click count',
            ], 500);
        }
    }


    public function removeDuplicated(Request $request, $id)
    {
        $session_id = $request->query('session_id');
        $authUser = $request->user();

        // Find the URL record for the correct owner
        if ($authUser) {
            $url = Url::where('id', $id)
                ->where('user_id', $authUser->id)
                ->first();
        } else {
            $url = Url::where('id', $id)
                ->where('session_id', $session_id)
                ->first();
        }

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'URL not found',
            ], 404);
        }

        try {
            // Delete all records with the same URL except the current one
            $query = Url::where('url', $url->url)
                ->where('id', '!=', $id);

            // Restrict to same user or same session
            if ($authUser) {
                $query->where('user_id', $authUser->id);
            } else {
                $query->where('session_id', $session_id);
            }

            $deletedCount = $query->delete();

            return response()->json([
                'success' => true,
                'message' => 'Duplicated URLs removed successfully',
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove duplicated URLs',
            ], 500);
        }
    }
}