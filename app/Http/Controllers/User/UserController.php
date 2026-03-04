<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class UserController extends Controller
{
    public function index()
    {
        $users = User::with('profile', 'medicalHistory')->get();
        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::with('profile', 'medicalHistory')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

   public function individualUsers(Request $request)
{
    try {
        $query = User::role('individual'); // Spatie scope

        if ($request->has('email')) {
            $email = $request->email;
            $query->where('email', 'like', "%{$email}%"); // partial match
        }

        $users = $query->select('id', 'name', 'email')->get(); // id added

        if ($users->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No individual users found.'
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
        
        // ১. মেইন ইউজার এবং কোচ সেট করা লক্ষ্যমাত্রা লোড করা
        $user = \App\Models\User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        // ২. বিভিন্ন লগ টেবিল থেকে এই সপ্তাহের ডাটা রিড করা
        $activityLogs = \DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $hydrationLogs = \DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $nutritionLogs = \DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $stressLogs = \DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();

        $totalSteps = $activityLogs->sum('daily_steps'); //
        $avgSleep = $activityLogs->avg('sleep_hours') ?? 0; //
        $totalWater = $activityLogs->sum('water_glasses'); //
        
        $latestWeight = $activityLogs->whereNotNull('weight')->last()->weight ?? ($user->profile->weight ?? 0);
        $weightDiff = $latestWeight - ($user->targetGoals->target_weight ?? 0);

        $bmi = 0;
        if ($user->profile->height > 0 && $latestWeight > 0) {
            $heightInMeters = $user->profile->height / 100;
            $weightInKg = $latestWeight * 0.453592;
            $bmi = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => $this->calculateWellnessScore($id), 
                    'days_active' => $activityLogs->unique('log_date')->count() . '/7',
                    'data_logged_entries' => $activityLogs->count() + $nutritionLogs->count() + $stressLogs->count(),
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight . ' lbs',
                        'status' => $weightDiff > 0 ? "+{$weightDiff} lbs above ideal" : "On track",
                        'coach_target' => ($user->targetGoals->target_weight ?? 'N/A') . ' lbs' //
                    ],
                    'bmi' => [
                        'current' => $bmi,
                        'status' => $bmi > 24.9 ? 'Higher than recommended' : 'Healthy range',
                        'coach_target' => 26.0
                    ],
                    'nutrition' => [
                        'last_meal_balance' => $nutritionLogs->last()->meal_balance ?? 'N/A', //
                        'protein_servings' => $nutritionLogs->sum('protein_servings'),
                        'note' => $user->adjustProgram->note ?? 'Follow coach plan'
                    ],
                    'daily_steps' => [
                        'current' => $totalSteps, // activity_logs থেকে
                        'coach_plan' => ($user->targetGoals->daily_step_goal ?? 0) . ' steps' //
                    ],
                    'sleep_hours' => [
                        'current' => round($avgSleep, 1) . ' Hrs',
                        'coach_plan' => $user->adjustProgram->sleep_target_range ?? 'N/A' //
                    ],
                    'hydration' => [
                        'current_glasses' => $totalWater,
                        'target' => $user->adjustProgram->hydration_target ?? 'N/A'
                    ],
                    'stress_and_mood' => [
                        'latest_mood' => $stressLogs->last()->mood ?? 'normal', //
                        'avg_stress_level' => round($stressLogs->avg('stress_level'), 1) ?? 0
                    ]
                ],

                'settings' => [
                    'show_graphs' => (bool) ($user->adjustProgram->show_progress_graphs ?? true),
                    'show_ai' => (bool) ($user->adjustProgram->show_ai_insights ?? true)
                ]
            ]
        ]);
    }

    private function calculateWellnessScore($userId)
    {
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $activityCount = \DB::table('activity_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();
        $avgStress = \DB::table('stress_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->avg('stress_level') ?? 3;
        $nutritionCount = \DB::table('nutrition_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();

        $activityScore = min(($activityCount / 7) * 50, 50);
        
        $nutritionScore = min(($nutritionCount / 7) * 30, 30);
        
        $stressScore = (5 - $avgStress) * 4;

        return round($activityScore + $nutritionScore + $stressScore);
    }

    public function getLogReport(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = \App\Models\User::with(['profile', 'medicalHistory'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $activityData = \DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $hydrationData = \DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepData = \DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressData = \DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionData = \DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        $metrics = [
            'activity' => [
                'avg_steps' => round($activityData->sum('daily_steps') / $days, 0),
                'total_steps' => $activityData->sum('daily_steps'),
                'weight_trend' => $activityData->pluck('weight', 'log_date'),                                          
            ],
            'hydration' => [
                'avg_water_glasses' => round($hydrationData->sum('water_glasses') / $days, 1),
                'total_water' => $hydrationData->sum('water_glasses'),
            ],
            'sleep' => [
                'avg_sleep_hours' => round($sleepData->sum('sleep_hours') / $days, 1),
                'total_logged_days' => $sleepData->count(),
                'consistency' => $sleepData->count() . " / $days days logged",
            ],
            'stress_and_mood' => [
                'avg_stress_level' => round($stressData->avg('stress_level') ?? 0, 1),
                'dominant_mood' => $stressData->groupBy('mood')->map->count()->sortDesc()->keys()->first() ?? 'N/A',
            ],
            'nutrition' => [
                'preferred_meal_balance' => $nutritionData->groupBy('meal_balance')->map->count()->sortDesc()->keys()->first() ?? 'N/A',
                'avg_protein_servings' => round($nutritionData->sum('protein_servings') / $days, 1),
            ]
        ];

        $dailyBreakdown = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            
            $dailyBreakdown[] = [
                'date'  => $date,
                'steps' => $activityData->where('log_date', $date)->first()->daily_steps ?? 0,
                'sleep' => $sleepData->where('log_date', $date)->first()->sleep_hours ?? 0,
                'water' => $hydrationData->where('log_date', $date)->first()->water_glasses ?? 0,
                'weight' => $activityData->where('log_date', $date)->first()->weight ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'report_period' => "Past $days Days",
            'user_info' => [
                'name' => $user->name,
                'profile' => $user->profile,
                'medical_history' => $user->medicalHistory,
            ],
            'metrics' => $metrics,
            'daily_breakdown' => $dailyBreakdown
        ]);
    }
}
