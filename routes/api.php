<?php

use App\Http\Controllers\ActivityLog\ActivityController;
use App\Http\Controllers\AdjustProgram\AdjustProgramController;
use App\Http\Controllers\Ads\AdsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\TermsAndConditionController;
use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\HydrationLog\HydrationController;
use App\Http\Controllers\NutritionLog\NutritionController;
use App\Http\Controllers\ProgramsSet\ProgramsSetController;
use App\Http\Controllers\SleepLog\SleepController;
use App\Http\Controllers\StressLog\StressController;
use App\Http\Controllers\Subscription\IndividualPlanController;
use App\Http\Controllers\Subscription\ProfessionalPlanController;
use App\Http\Controllers\TargetGoal\TargetGoalController;
use App\Http\Controllers\User\UserProfileController;

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

        Route::get('profile', [UserProfileController::class, 'index']);
        Route::post('profile', [UserProfileController::class, 'storeAndUpdate']);
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


        Route::post('/activity', [ActivityController::class, 'store']);
        Route::get('/activity/{user_id}', [ActivityController::class, 'index']);

        Route::post('/hydration', [HydrationController::class, 'store']);
        Route::get('/hydration/{user_id}', [HydrationController::class, 'index']);

        Route::post('/sleep', [SleepController::class, 'store']);
        Route::get('/sleep/{user_id}', [SleepController::class, 'index']);

        Route::post('/stress', [StressController::class, 'store']);
        Route::get('/stress/{user_id}', [StressController::class, 'index']);

        Route::post('/nutrition', [NutritionController::class, 'store']);
        Route::get('/nutrition/{user_id}', [NutritionController::class, 'index']);

        Route::post('/goals', [TargetGoalController::class, 'store']);
        Route::get('/goals/{user_id}', [TargetGoalController::class, 'show']);

        Route::post('/adjust-program', [AdjustProgramController::class, 'store']);
        Route::get('/adjust-program/{user_id}', [AdjustProgramController::class, 'show']);

        Route::post('/ads', [AdsController::class, 'store']);
        Route::get('/ads', [AdsController::class, 'index']);

        Route::post('/programs-set', [ProgramsSetController::class, 'store']);
        Route::get('/programs-set', [ProgramsSetController::class, 'index']);

    });
});
