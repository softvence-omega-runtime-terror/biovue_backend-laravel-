<?php

use App\Http\Controllers\ActivityLog\ActivityController;
use App\Http\Controllers\AdjustProgram\AdjustProgramController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\Ads\AdsController;
use App\Http\Controllers\Schedule\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\TermsAndConditionController;
use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\HydrationLog\HydrationController;
use App\Http\Controllers\HydrationLog\HydrationReportController;
use App\Http\Controllers\Message\MessageController;
use App\Http\Controllers\NutritionLog\NutritionController;
use App\Http\Controllers\ProgramsSet\ProgramsSetController;
use App\Http\Controllers\SleepLog\SleepController;
use App\Http\Controllers\StressLog\StressController;
use App\Http\Controllers\StressLog\StressReportController;
use App\Http\Controllers\Subscription\IndividualPlanController;
use App\Http\Controllers\Subscription\PlanController;
use App\Http\Controllers\Subscription\ProfessionalPlanController;
use App\Http\Controllers\TargetGoal\TargetGoalController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\User\UserController;


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

    Route::get('ads', [AdsController::class, 'index']);

    // ----------------------------
    // Protected Routes (Require Auth)
    // ----------------------------
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('profile', [UserProfileController::class, 'index']);
        Route::post('profile', [UserProfileController::class, 'storeAndUpdate']);
        Route::get('user-reports', [UserController::class, 'getUserReport']);
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
        Route::get('/hydration-report', [HydrationReportController::class, 'getHydrationReport']);

        Route::post('/sleep-logs', [SleepController::class, 'store']);
        Route::get('/sleep-logs', [SleepController::class, 'index']);
    
        Route::get('/sleep-logs/{id}', [SleepController::class, 'show']);
        Route::get('/sleep-report', [SleepController::class, 'getSleepReport']);

        Route::apiResource('stress-logs', StressController::class);
        Route::get('/stress-report', [StressReportController::class, 'getStressReport']);

        Route::get('/nutrition-logs', [NutritionController::class, 'index']);
        Route::post('/nutrition-logs', [NutritionController::class, 'store']);
        Route::get('/nutrition-logs/{id}', [NutritionController::class, 'show']);
        Route::delete('/nutrition-logs/{id}', [NutritionController::class, 'destroy']);
        Route::get('/nutrition-report', [NutritionController::class, 'getNutritionReport']);

        Route::get('/calendar-schedules', [ScheduleController::class, 'index']);
        Route::post('/schedule-checkin', [ScheduleController::class, 'storeSchedule']);
        Route::post('/send-reminder', [ScheduleController::class, 'sendReminder']);
        Route::get('/my-reminders', [ScheduleController::class, 'getMyReminders']);

        Route::post('/goals', [TargetGoalController::class, 'store']);
        Route::get('/goals/{id}', [TargetGoalController::class, 'show']);

        Route::post('/adjust-program', [AdjustProgramController::class, 'store']);
        Route::get('/adjust-program/{id}', [AdjustProgramController::class, 'show']);

        Route::post('ads', [AdsController::class, 'storeOrUpdate']);
        Route::delete('ads/{id}', [AdsController::class, 'destroy']);
        Route::get('ads/admin', [AdsController::class, 'adminIndex']);
        Route::post('ads/toggle-status/{id}', [AdsController::class, 'toggleStatus']);

        Route::get('/admin/overview', [DashboardController::class, 'getAdminStats']);
        Route::get('/admin/reports', [ReportController::class, 'getReports']);

        Route::get('/admin/users', [UserListController::class, 'getUser']);
        Route::get('/admin/users/{id}', [UserListController::class, 'getUserById']);
        Route::delete('/admin/users/{id}', [UserListController::class, 'destroy']);

        Route::apiResource('plans', PlanController::class);
        Route::post('plans/toggle-status/{id}', [PlanController::class, 'toggleStatus']);
        Route::get('/plans/{type}', [PlanController::class, 'getPlansByType']);


        //trainer dashbaord 
       Route::resource('program-sets', ProgramsSetController::class);
       
        Route::get('users/individuals', [UserController::class, 'individualUsers']);
        Route::post('program-sets/assign-users', [ProgramsSetController::class, 'assignUsers']);

        // Messaging
        Route::post('/messages/send', [MessageController::class, 'sendMessage']);
        Route::get('/messages/{id}', [MessageController::class, 'getMessages']);
        Route::get('/conversations', [MessageController::class, 'getConversations']);

    });
});
