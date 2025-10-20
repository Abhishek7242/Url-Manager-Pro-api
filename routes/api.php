<?php

use App\Http\Controllers\OtpController;
use App\Http\Controllers\UrlController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/*
 * Stateless/public endpoints
 */

Route::post('/user/make_user', [UrlController::class, 'makeUser']);
Route::post('/user/signup', [UrlController::class, 'signup']);
Route::post('/user/signup/sendotp', [OtpController::class, 'sendOtp']);
Route::post('/user/session', [UserController::class, 'storeSessionData']);
Route::post('/user/logout', [UserController::class, 'logout']);


/*
 * Routes that need session + sanctum: ensure StartSession runs BEFORE Sanctum.
 * The middleware array order matters: StartSession first, then EnsureFrontendRequestsAreStateful (sanctum), then auth if needed.
 */
Route::middleware([StartSession::class, \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class])->group(function () {

    // Login and OTP verification need session cookie creation
    Route::post('/user/login', [UserController::class, 'login']);
    Route::post('/user/signup/verifyotp', [OtpController::class, 'verifyOtp']);
    
    Route::post('/user/forgotpassword/sendotp', [UserController::class, 'sendForgotPasswordOtp']);
    Route::post('/user/forgotpassword/verifyotp', [UserController::class, 'verifyForgotPasswordOtp']);
    Route::post('/user/newpassword', [UserController::class, 'resetPassword']);

    // If you want authenticated routes under same middleware, add auth:sanctum as well:
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user', [UserController::class, 'fetchUser']);
        Route::post('/url/create', [UrlController::class, 'store']);
        Route::get('/get-urls', [UrlController::class, 'getAll']);
        Route::post('/url/keep-this/{id}', [UrlController::class, 'removeDuplicated']);
        Route::post('/user/url/update-click-count/{id}', [UrlController::class, 'updateClickCount']);
        Route::delete('/user/url/delete/{id}', [UrlController::class, 'destroy']);
        Route::put('/user/url/edit/{id}', [UrlController::class, 'edit']);
        Route::get('/user/url/get-data/{id}', [UrlController::class, 'getById']);
    });
});

// Public guest endpoints
Route::get('/guest/url/get-data/{id}', [UrlController::class, 'getById']);
Route::post('/url/add', [UrlController::class, 'store']);
Route::get('/geturls', [UrlController::class, 'getAll']);
Route::put('/guest/url/edit/{id}', [UrlController::class, 'edit']);
Route::post('/guest/url/update-click-count/{id}', [UrlController::class, 'updateClickCount']);
Route::post('/url/keep/{id}', [UrlController::class, 'removeDuplicated']);
Route::delete('/guest/url/delete/{id}', [UrlController::class, 'destroy']);

// Session info endpoint — needs StartSession before Sanctum
Route::middleware([StartSession::class, \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class])
    ->get('/session-info', [UrlController::class, 'getSessionInfo']);

// php artisan install:api to make api file