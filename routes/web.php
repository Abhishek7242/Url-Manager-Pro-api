<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Sanctum CSRF cookie endpoint for SPA authentication
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});

Route::get('/debug-session', function (Request $r) {
    return response()->json([
        'session_id' => session()->getId(),
        'session_all' => session()->all()
    ]);
});
// shows if Laravel considers request authenticated
Route::get('/debug-auth', function (Request $r) {
    return response()->json([
        'auth_user_id' => auth()->id(),
        'auth_user' => auth()->user() ? auth()->user()->only(['id', 'email', 'name']) : null,
        'session_id' => session()->getId(),
        'session_all' => session()->all(),
    ]);
});

// show Set-Cookie headers from the verify endpoint response in DevTools
// Ensure you call these endpoints with credentials: 'include'