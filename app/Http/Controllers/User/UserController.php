<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class UserController extends Controller
{
   public function individualUsers(Request $request)
{
    try {
        $query = User::role('individual'); // Spatie scope

        if ($request->has('email')) {
            $email = $request->email;
            $query->where('email', 'like', "%{$email}%"); // partial match
        } else {
            // if email→ user not found
            return response()->json([
                'status' => false,
                'message' => 'User not found. Please provide an email to search.'
            ], 404);
        }

        $users = $query->select('id', 'name', 'email')->get(); // id added

        if ($users->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No individual users found with this email keyword.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $users
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch users. Error: '.$e->getMessage()
        ], 500);
    }
}


    public function getUserReport(Request $request)
    {
        try {
            $user = $request->user();

            $profile = DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->first();

            if (!$profile) {
                return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
            }

            $heightInMeters = $profile->height / 100;
            $bmi = $heightInMeters > 0 ? round($profile->weight / ($heightInMeters * $heightInMeters), 1) : 0;

            return response()->json([
                'success' => true,
                'user_info' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'wellness_stats' => [
                    'wellness_score' => $profile->stress_level ?? 0,
                    'days_active' => $profile->workout_week ?? '0/7',
                    'data_logged' => 12,
                ],
                'health_overview' => [
                    'current_weight' => $profile->weight . ' lbs',
                    'bmi' => $bmi,
                    'nutrition_quality' => $profile->overall_diet_quality ?? 'N/A',
                    'weekly_workouts' => $profile->workout_week ?? '0 session',
                    'daily_steps' => number_format($profile->daily_step ?? 0),
                    'sleep_hours' => ($profile->sleep_hour ?? 0) . ' hrs',
                ],
                'fitness_goals' => [
                    'is_athletic' => (bool)$profile->is_athletic,
                    'toned' => (bool)$profile->toned,
                    'lean' => (bool)$profile->lean,
                    'muscular' => (bool)$profile->muscular,
                    'curvy_fit' => (bool)$profile->curvy_fit,
                ],
                'today_focus' => [
                    'diet' => 'Improve ' . ($profile->overall_diet_quality ?? 'Diet') . ' Quality',
                    'sleep' => 'Maintain ' . ($profile->sleep_hour ?? 0) . ' hours sleep'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


    public function getHealthReport($userId = null)
    {
        $id = $userId ?: auth()->id();
        
        $user = \App\Models\User::with(['profile', 'targetGoals', 'adjustPrograms'])->find($id);

        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $thisWeekLogs = \DB::table('daily_logs')
            ->where('user_id', $id)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get();

        $totalSteps = $thisWeekLogs->sum('steps');
        $avgSleep = $thisWeekLogs->avg('sleep_hours') ?? 0; 
        $entriesCount = $thisWeekLogs->count();

        $currentWeight = $user->profile->weight ?? 0;
        $targetWeight = $user->targetGoals->target_weight ?? 0;
        $weightDiff = $currentWeight - $targetWeight;

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => $this->calculateWellnessScore($id),
                    'days_active' => $thisWeekLogs->pluck('created_at')->unique()->count() . '/7',
                    'data_logged_this_week' => $entriesCount,
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $currentWeight . ' lbs',
                        'status' => $weightDiff > 0 ? "+{$weightDiff} lbs above ideal" : "On track",
                        'coach_target' => $targetWeight . ' lbs'
                    ],
                    'weekly_workouts' => [
                        'completed' => $thisWeekLogs->where('workout_done', true)->count(), 
                        'coach_plan' => $user->adjustPrograms->weekly_workouts ?? 'N/A'
                    ],
                    'daily_steps' => [
                        'current' => $totalSteps,
                        'coach_plan' => $user->targetGoals->daily_step_goal ?? 0
                    ],
                    'sleep_hours' => [
                        'current' => round($avgSleep, 1) . ' Hrs',
                        'coach_plan' => $user->adjustPrograms->sleep_target_range ?? 'N/A'
                    ]
                ],

                'ui_settings' => [
                    'show_progress_graphs' => (bool)$user->adjustPrograms->show_progress_graphs,
                    'show_ai_insights' => (bool)$user->adjustPrograms->show_ai_insights,
                ]
            ]
        ]);
    }
}
