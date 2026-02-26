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


        Route::get('/activity-logs', [ActivityController::class, 'index']);
        Route::post('/activity-logs', [ActivityController::class, 'store']); 
        Route::get('/activity-logs/{id}', [ActivityController::class, 'show']); 
        Route::delete('/activity-logs/{id}', [ActivityController::class, 'destroy']); 
        Route::get('/activity-report', [ActivityController::class, 'getActivityReport']);

        Route::get('/hydration-logs', [HydrationController::class, 'index']);
        Route::post('/hydration-logs', [HydrationController::class, 'store']);
        Route::get('/hydration-logs/{id}', [HydrationController::class, 'show']);
        Route::delete('/hydration-logs/{id}', [HydrationController::class, 'destroy']);

        Route::post('/sleep-logs', [SleepController::class, 'store']);
        Route::get('/sleep-logs', [SleepController::class, 'index']);
    
        Route::get('/sleep-logs/{id}', [SleepController::class, 'show']);
        Route::get('getSleepReport',[SleepController::class, 'getSleepReport']);

        Route::apiResource('stress-logs', StressController::class);

        Route::get('/nutrition-logs', [NutritionController::class, 'index']);
        Route::post('/nutrition-logs', [NutritionController::class, 'store']);
        Route::get('/nutrition-logs/{id}', [NutritionController::class, 'show']);
        Route::delete('/nutrition-logs/{id}', [NutritionController::class, 'destroy']);
        Route::get('/nutrition-report', [NutritionController::class, 'getNutritionReport']);

        Route::post('/goals', [TargetGoalController::class, 'store']);
        Route::get('/goals/{id}', [TargetGoalController::class, 'show']);

        Route::post('/adjust-program', [AdjustProgramController::class, 'store']);
        Route::get('/adjust-program/{id}', [AdjustProgramController::class, 'show']);

        Route::post('/ads', [AdsController::class, 'store']);
        Route::get('/ads', [AdsController::class, 'index']);

        Route::post('/programs-set', [ProgramsSetController::class, 'store']);
        Route::get('/programs-set', [ProgramsSetController::class, 'index']);

    });
});
