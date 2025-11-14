<?php

use App\Providers\IndexNowService;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect()->away('https://urlmg.com/');
});

// IndexNow key file endpoint - required by IndexNow protocol
// The key file must be accessible at: {APP_URL}/{INDEXNOW_KEY}.txt
// This route is placed early to avoid conflicts with React routing
Route::get('/{key}.txt', function ($key) {
    $expectedKey = env('INDEXNOW_KEY');

    // Only serve the key file if the requested key matches
    if ($key === $expectedKey && $expectedKey) {
        return response($expectedKey, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    abort(404);
})->where('key', '[a-zA-Z0-9_-]+');

// Route::get('/submit-indexnow', function (IndexNowService $service) {
//     // Fetch homepage and optionally sitemap if you prefer manual list:
//     $result = $service->submit(['https://urlmg.com/']);
//     return response()->json($result);
// });
// Sanctum CSRF cookie endpoint for SPA authentication
// Route::get('/sanctum/csrf-cookie', function () {
//     return response()->json(['message' => 'CSRF cookie set']);
// });

Route::get('/debug-session', function (Request $r) {
    return response()->json([
        'session_id' => session()->getId(),
        'session_all' => session()->all()
    ]);
});
// shows if Laravel considers request authenticated
Route::get('/debug-auth', function (Request $r) {
    $user = \Illuminate\Support\Facades\Auth::user();
    return response()->json([
        'auth_user_id' => \Illuminate\Support\Facades\Auth::id(),
        'auth_user' => $user ? [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ] : null,
        'session_id' => session()->getId(),
        'session_all' => session()->all(),
    ]);
});

// show Set-Cookie headers from the verify endpoint response in DevTools
// Ensure you call these endpoints with credentials: 'include'