<?php

use App\Http\Controllers\OtpController;
use App\Http\Controllers\UrlController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/user/make_user', [UrlController::class, 'makeUser']);
Route::post('/user/signup', [UrlController::class, 'signup']);
Route::post('/user/signup/sendotp', [OtpController::class, 'sendOtp']);
Route::post('/user/session', [UserController::class, 'storeSessionData']);

// Apply stateful middleware only to verifyotp since it needs to set session cookies
Route::middleware('sanctum')->group(function () {
    Route::post('/user/login', [UserController::class, 'login']);
    Route::post('/user/signup/verifyotp', [OtpController::class, 'verifyOtp']);
});
// Public logout route - clears session even when not authenticated

Route::post('/user/logout', [UserController::class, 'logout']);
Route::middleware(['sanctum', 'auth:sanctum'])->group(function () {
    Route::post('/url/create', [UrlController::class, 'store']);
    Route::get('/user', [UserController::class, 'fetchUser']);
    Route::get('/get-urls', [UrlController::class, 'getAll']);
    Route::post('/url/keep-this/{id}', [UrlController::class, 'removeDuplicated']);
    Route::post('/user/url/update-click-count/{id}', [UrlController::class, 'updateClickCount']);
    Route::delete('/user/url/delete/{id}', [UrlController::class, 'destroy']);
    Route::put('/user/url/edit/{id}', [UrlController::class, 'edit']);
    Route::get('/user/url/get-data/{id}', [UrlController::class, 'getById']);
});
Route::get('/guest/url/get-data/{id}', [UrlController::class, 'getById']);
Route::post('/url/add', [UrlController::class, 'store']);
Route::get('/geturls', [UrlController::class, 'getAll']);
Route::put('/guest/url/edit/{id}', [UrlController::class, 'edit']);
Route::post('/guest/url/update-click-count/{id}', [UrlController::class, 'updateClickCount']);
Route::post('/url/keep/{id}', [UrlController::class, 'removeDuplicated']);
Route::delete('/guest/url/delete/{id}', [UrlController::class, 'destroy']);
// Apply stateful middleware to session-info endpoint
Route::middleware('sanctum')->group(function () {
    Route::get('/session-info', [UrlController::class, 'getSessionInfo']);
});







// php artisan install:api to make api file