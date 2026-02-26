<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\TermsAndConditionController;
use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\Subscription\IndividualPlanController;
use App\Http\Controllers\Subscription\ProfessionalPlanController;

Route::prefix('v1')->group(function () {

    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [SignUpController::class, 'register']);
    Route::post('verify-otp', [SignUpController::class, 'verifyOtp']);

    Route::post('login', [LoginController::class, 'login']);
    Route::post('resend-otp', [LoginController::class, 'resendOtp']);

    Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);

    // Firebase using google login
    Route::post('firebase-login', [FirebaseAuthController::class, 'loginWithFirebase']);

    // Public - Everyone can view plans
    Route::resource('individual-plans', IndividualPlanController::class)->only(['index']);
    Route::resource('professional-plans', ProfessionalPlanController::class)->only(['index']);

    // Public Route: Get Terms & Conditions
    Route::get('terms', [TermsAndConditionController::class, 'get']);

    // Stripe Webhook
    Route::post('payment/webhook', [PaymentController::class, 'handleWebhook']);
     Route::get('/payment/success', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/payment/cancel', [PaymentController::class, 'paymentCancel'])->name('payment.cancel');



    // ----------------------------
    // Protected Routes (Require Auth)
    // ----------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Protected - create/delete
        Route::post('individual-plans', [IndividualPlanController::class, 'store']);
        Route::delete('individual-plans/{id}', [IndividualPlanController::class, 'destroy']);

        Route::post('professional-plans', [ProfessionalPlanController::class, 'store']);
        Route::delete('professional-plans/{id}', [ProfessionalPlanController::class, 'destroy']);

        //Payment for individual user & professional user

           //subscription payment
        Route::post('/payment/process', [PaymentController::class, 'processPayment']);
        Route::get('/all-payments', [PaymentController::class, 'index'])->name('payment.index');//admin show all
        Route::get('/payment/show', [PaymentController::class, 'show']);



        // Admin Route: Create/Update Terms & Conditions
        Route::post('terms', [TermsAndConditionController::class, 'save']);

        Route::post('logout', [LoginController::class, 'logout']);
    });
});
