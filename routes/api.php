<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;

Route::prefix('v1')->group(function () {

    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [SignUpController::class, 'register']);         // User registration
    Route::post('verify-otp', [SignUpController::class, 'verifyOtp']);     // OTP verification

    Route::post('login', [LoginController::class, 'login']);                // Login
    Route::post('resend-otp', [LoginController::class, 'resendOtp']);      // Resend OTP

    Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']); // Forgot password
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);   // Reset password

    // ----------------------------
    // Protected Routes (Require Auth)
    // ----------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', function () {
            return auth()->user();
        });

        Route::post('logout', [LoginController::class, 'logout']);          // Logout
        // অন্যান্য protected routes এখানে add করতে পারো
    });
});
