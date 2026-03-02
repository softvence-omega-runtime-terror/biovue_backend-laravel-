<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function updateSettings(Request $request) 
{
    $user = Auth::user();

    $validated = $request->validate([
        'coach_messages'              => 'boolean',
        'goal_updates'                => 'boolean',
        'ai_insights'                 => 'boolean',
        'missed_checkin_alerts'       => 'boolean',
        'program_milestone_updates'   => 'boolean',
        'weekly_summary_email'        => 'boolean',
        'auto_remind_missed_checkins' => 'boolean',
        'default_reminder_time'       => 'string',
        'check_in_reminder_alerts'    => 'boolean',
        'subscription_updates'        => 'boolean',
    ]);

    $user->notificationSettings()->updateOrCreate(
        ['user_id' => $user->id],
        $validated
    );

    return response()->json([
        'status' => 'success',
        'message' => 'Settings updated successfully!',
        'data' => $user->notificationSettings->refresh()
    ]);
}
}
