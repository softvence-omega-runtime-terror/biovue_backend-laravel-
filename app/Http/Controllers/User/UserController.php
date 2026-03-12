<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
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
        
        $user = \App\Models\User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->get();

        $totalSteps = $activityLogs->sum('daily_steps');
        $avgSleep = $activityLogs->avg('sleep_hours') ?? 0;
        $totalWater = $activityLogs->sum('water_glasses'); 
        
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
                        'coach_target' => ($user->targetGoals->target_weight ?? 'N/A') . ' lbs' 
                    ],
                    'bmi' => [
                        'current' => $bmi,
                        'status' => $bmi > 24.9 ? 'Higher than recommended' : 'Healthy range',
                        'coach_target' => 26.0
                    ],
                    'nutrition' => [
                        'last_meal_balance' => $nutritionLogs->last()->meal_balance ?? 'N/A',
                        'protein_servings' => $nutritionLogs->sum('protein_servings'),
                        'note' => $user->adjustProgram->note ?? 'Follow coach plan'
                    ],
                    'daily_steps' => [
                        'current' => $totalSteps,
                        'coach_plan' => ($user->targetGoals->daily_step_goal ?? 0) . ' steps'
                    ],
                    'sleep_hours' => [
                        'current' => round($avgSleep, 1) . ' Hrs',
                        'coach_plan' => $user->adjustProgram->sleep_target_range ?? 'N/A'
                    ],
                    'hydration' => [
                        'current_glasses' => $totalWater,
                        'target' => $user->adjustProgram->hydration_target ?? 'N/A'
                    ],
                    'stress_and_mood' => [
                        'latest_mood' => $stressLogs->last()->mood ?? 'normal',
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

        $activityCount = DB::table('activity_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();
        $avgStress = DB::table('stress_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->avg('stress_level') ?? 3;
        $nutritionCount = DB::table('nutrition_logs')->where('user_id', $userId)->whereBetween('log_date', [$startOfWeek, $endOfWeek])->count();

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

        $user = User::with(['profile', 'medicalHistory', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        $latestWeight = $activityLogs->last()->weight ?? ($user->profile->weight ?? 0);
        $bmiScore = 0;
        if ($user->profile->height > 0 && $latestWeight > 0) {
            $heightInMeters = $user->profile->height / 100;
            $weightInKg = $latestWeight * 0.453592; 
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        $nutritionEntriesCount = $nutritionLogs->count();
        $nutritionScore = min(($nutritionEntriesCount / $days) * 100, 100); 

        return response()->json([
            'success' => true,
            'health_overview' => [
                'weight' => [
                    'current' => $latestWeight . " lbs",
                    'coach_target' => ($user->targetGoals->target_weight ?? 'N/A') . " lbs",
                    'insight' => $user->targetGoals->notes ?? "Based on your fitness goals."
                ],
                'bmi' => [
                    'score' => $bmiScore,
                    'coach_target' => $user->targetGoals->target_bmi ?? 26.0, 
                    'status_label' => $bmiScore > 24.9 ? "Higher than recommended range" : "Healthy range"
                ],
                'nutrition_quality' => [
                    'score' => round($nutritionScore), 
                    'status' => $nutritionLogs->last()->meal_balance ?? 'Balanced',
                    'coach_note' => $user->adjustPrograms->note ?? "Improve consistency on weekends" //
                ],
                'daily_steps' => [
                    'current' => (int) $activityLogs->avg('daily_steps'),
                    
                    'coach_plan' => ($user->targetGoals->daily_step_goal ?? 0) . " steps"
                ],
                'sleep_hours' => [
                    'current' => round($activityLogs->avg('sleep_hours'), 1) . " Hrs",
                    
                    'coach_plan' => $user->adjustPrograms->sleep_target_range ?? '7-8 Hrs'
                ]
            ]
        ]);
    }

    public function getDashboardData(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepLogs = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        $latestWeight = $activityLogs->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 190.0;
        
        $bmiScore = 0;
        if ($user->profile->height > 0 && $latestWeight > 0) {
            $heightInMeters = $user->profile->height / 100;
            $weightInKg = $latestWeight * 0.453592;
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => [
                        'value' => 72, 
                        'max' => 100,
                        'trend' => "+4 vs last week",
                        'label' => "Coach-tracked"
                    ],
                    'days_active' => [
                        'current' => $activityLogs->unique('log_date')->count(),
                        'total' => $days,
                        'status' => "On track for your goal"
                    ],
                    'data_logged' => [
                        'count' => $activityLogs->count() + $nutritionLogs->count() + $stressLogs->count(),
                        'label' => "Entries this week"
                    ]
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight,
                        'unit' => 'lbs',
                        'diff_label' => ($latestWeight - $targetWeight) > 0 ? "+" . round($latestWeight - $targetWeight, 1) . " lbs above ideal" : "On track",
                        'insight' => "Based on standard wellness ranges"
                    ],
                    'bmi' => [
                        'score' => $bmiScore,
                        'range' => "18.5 - 24.9",
                        'status' => $bmiScore > 24.9 ? "Your Body fat is higher than the recommended range" : "Healthy range"
                    ],
                    'nutrition' => [
                        'score' => 80, 
                        'status' => $nutritionLogs->last()->meal_balance ?? 'Balanced',
                        'message' => "your meals are fuelling you well today"
                    ],
                    'workouts' => [
                        'completed' => $activityLogs->where('daily_steps', '>', 5000)->count(),
                        'goal' => "4-5 sessions",
                        'insight' => "Regular workouts help reach your wellness goal faster"
                    ],
                    'steps' => [
                        'current' => (int) ($activityLogs->avg('daily_steps') ?? 0),
                        'goal' => ($user->targetGoals->daily_step_goal ?? 8000) . " steps",
                        'insight' => "Increasing daily movement improves overall health"
                    ],
                    'sleep' => [
                        'avg' => round($sleepLogs->avg('sleep_hours') ?? 0, 1),
                        'goal' => "7-9 hours",
                        'insight' => "Quality sleep supports recovery & focus"
                    ]
                ],

                'consistency_metrics' => [
                    [
                        'title' => 'Sleep',
                        'avg' => round($sleepLogs->avg('sleep_hours'), 1) . " hrs avg this week",
                        'status' => $sleepLogs->count() >= ($days * 0.7) ? "ON TRACK" : "Need Attention",
                        'ratio' => $sleepLogs->count() . "/$days Days"
                    ],
                    [
                        'title' => 'Activity',
                        'avg' => number_format($activityLogs->avg('daily_steps') ?? 0) . " steps avg this week",
                        'status' => ($activityLogs->avg('daily_steps') ?? 0) >= 8000 ? "ON TRACK" : "Need Attention",
                        'ratio' => $activityLogs->count() . "/$days DAYS"
                    ],
                    [
                        'title' => 'Hydration',
                        'avg' => round($hydrationLogs->avg('water_glasses'), 1) . " glasses avg this week",
                        'status' => $hydrationLogs->count() >= ($days * 0.7) ? "ON TRACK" : "Need Attention",
                        'ratio' => $hydrationLogs->count() . "/$days DAYS"
                    ],
                    [
                        'title' => 'Nutrition',
                        'avg' => round($nutritionLogs->avg('protein_servings'), 1) . " serv/day avg this week",
                        'status' => $nutritionLogs->count() >= ($days * 0.5) ? "ON TRACK" : "Need Attention",
                        'ratio' => $nutritionLogs->count() . "/$days Days"
                    ],
                    [
                        'title' => 'Stress',
                        'avg' => round($stressLogs->avg('stress_level'), 1) . "/10 avg this week",
                        'status' => $stressLogs->avg('stress_level') <= 3 ? "ON TRACK" : "Need Attention",
                        'ratio' => $stressLogs->count() . "/$days DAYS"
                    ]
                ]
            ]
        ]);
    }

    public function toggleActiveUser($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "User has been " . ($user->is_active ? "activated" : "deactivated") . "."
        ]);
    }


    public function userOverviewData(Request $request, $userId = null)
    {
        $id = $userId ?: auth()->id();
        $days = (int) $request->query('days', 7); 
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        $user = User::with(['profile', 'targetGoals', 'adjustProgram'])->find($id);
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        $activityLogs = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $hydrationLogs = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $sleepLogs = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $stressLogs = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();
        $nutritionLogs = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate, $endDate])->get();

        $latestWeight = $activityLogs->last()->weight ?? ($user->profile->weight ?? 0);
        $targetWeight = $user->targetGoals->target_weight ?? 0;
        
        $bmiScore = 0;
        if ($user->profile->height > 0 && $latestWeight > 0) {
            $heightInMeters = $user->profile->height / 100;
            $weightInKg = $latestWeight * 0.453592;
            $bmiScore = round($weightInKg / ($heightInMeters * $heightInMeters), 1);
        }

        $loggedCount = $activityLogs->count() + $nutritionLogs->count() + $stressLogs->count() + $hydrationLogs->count() + $sleepLogs->count();
        $wellnessScore = min(round(($loggedCount / ($days * 3)) * 100), 100);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'wellness_score' => [
                        'score' => $wellnessScore,
                        'max' => 100,
                        'footer' => "Coach-tracked"
                    ],
                    'days_active' => [
                        'current' => $activityLogs->unique('log_date')->count(),
                        'total' => $days,
                        'status' => "Based on your activity logs"
                    ],
                    'data_logged' => [
                        'count' => $loggedCount,
                        'label' => "Total entries in $days days"
                    ]
                ],

                'health_overview' => [
                    'weight' => [
                        'current' => $latestWeight,
                        'diff' => round($latestWeight - $targetWeight, 1),
                        'coach_target' => $targetWeight . " lbs",
                        'insight' => $user->targetGoals->notes ?? "Target updated by coach"
                    ],
                    'bmi' => [
                        'score' => $bmiScore,
                        'status' => $bmiScore > 24.9 ? "Above recommended range" : "Healthy range",
                        'coach_target' => $user->targetGoals->target_bmi ?? 26.0
                    ],
                    'nutrition' => [
                        'quality' => round(($nutritionLogs->count() / $days) * 100),
                        'last_balance' => $nutritionLogs->last()->meal_balance ?? 'N/A',
                        'coach_note' => $user->adjustPrograms->note ?? "Improve consistency"
                    ],
                    'workouts' => [
                        'count' => $activityLogs->where('daily_steps', '>', 5000)->count(),
                        'coach_plan' => $user->adjustPrograms->weekly_workouts ?? '3-4 session/week'
                    ],
                    'steps' => [
                        'avg' => (int) $activityLogs->avg('daily_steps'),
                        'coach_plan' => ($user->targetGoals->daily_step_goal ?? 0) . " steps"
                    ],
                    'sleep' => [
                        'avg' => round($sleepLogs->avg('sleep_hours'), 1),
                        'coach_plan' => $user->adjustPrograms->sleep_target_range ?? '7-8 Hrs'
                    ]
                ],

                'consistency' => [
                    'sleep' => $this->formatConsistency('Sleep', $sleepLogs, $days, 7),
                    'activity' => $this->formatConsistency('Activity', $activityLogs, $days, 6500, 'daily_steps'),
                    'hydration' => $this->formatConsistency('Hydration', $hydrationLogs, $days, 8, 'water_glasses'),
                    'nutrition' => $this->formatConsistency('Nutrition', $nutritionLogs, $days, 3, 'protein_servings'),
                    'stress' => $this->formatConsistency('Stress', $stressLogs, $days, 4, 'stress_level', true)
                ]
            ]
        ]);
    }

    private function formatConsistency($title, $logs, $totalDays, $target, $column = 'sleep_hours', $isStress = false)
    {
        $count = $logs->count();
        $avg = round($logs->avg($column) ?? 0, 1);
        
        $isOnTrack = $isStress ? ($avg <= $target) : ($count >= ($totalDays * 0.7));

        return [
            'title' => $title,
            'avg_text' => $avg . " avg this week",
            'status' => $isOnTrack ? "ON TRACK" : "Need Attention",
            'ratio' => "$count/$totalDays Days",
            'percentage' => round(($count / $totalDays) * 100)
        ];
    }

    public function userOverviewChart(Request $request)
    {
        $user = $request->user();
        $id = $user->id;
        
        $daysCount = (int) $request->query('days', 7); 
        $startDate = now()->subDays($daysCount - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $userData = \App\Models\User::with(['profile', 'targetGoals', 'adjustPrograms'])->find($id);

        $activity = DB::table('activity_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
        $hydration = DB::table('hydration_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
        $sleep = DB::table('sleep_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
        $stress = DB::table('stress_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();
        $nutrition = DB::table('nutrition_logs')->where('user_id', $id)->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])->get();

        $chartData = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $formattedDate = $date->toDateString();
            $actLog = $activity->where('log_date', $formattedDate)->first();
            $slpLog = $sleep->where('log_date', $formattedDate)->first();
            $nutLog = $nutrition->where('log_date', $formattedDate)->first();

            $chartData[] = [
                'label' => $daysCount <= 7 ? $date->format('D') : $date->format('d M'),
                'weight' => $actLog ? (float)$actLog->weight : null,
                'steps' => $actLog ? (int)$actLog->daily_steps : 0,
                'sleep_hours' => $slpLog ? (float)$slpLog->sleep_hours : 0,
                'nutrition' => [
                    'protein' => $nutLog ? (int)$nutLog->protein_servings : 0,
                    'carbs' => $nutLog ? (int)$nutLog->carbs_servings : 0,
                    'fats' => $nutLog ? (int)$nutLog->fats_servings : 0,
                ]
            ];
        }

        $latestWeight = $activity->last()->weight ?? ($userData->profile->weight ?? 0);
        $bmi = 0;
        if ($userData->profile->height > 0 && $latestWeight > 0) {
            $heightM = $userData->profile->height / 100;
            $weightK = $latestWeight * 0.453592; 
            $bmi = round($weightK / ($heightM * $heightM), 1);
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'wellness_score' => 72,
                'days_active' => $activity->unique('log_date')->count() . "/$daysCount",
                'entries_count' => $activity->count() + $nutrition->count() + $sleep->count()
            ],
            'health_overview' => [
                'weight' => [
                    'current' => $latestWeight,
                    'target' => $userData->targetGoals->target_weight ?? 0,
                    'note' => $userData->targetGoals->notes ?? "Target updated by coach"
                ],
                'bmi' => [
                    'score' => $bmi,
                    'target' => $userData->targetGoals->target_bmi ?? 26.0,
                    'status' => $bmi > 24.9 ? "Higher than range" : "Healthy"
                ],
                'steps' => [
                    'avg' => (int)$activity->avg('daily_steps'),
                    'target' => $userData->targetGoals->daily_step_goal ?? 6500
                ]
            ],
            'charts' => $chartData, 
            'consistency' => [
                'sleep' => $this->calcConsist('Sleep', $sleep, $daysCount, 7),
                'activity' => $this->calcConsist('Activity', $activity, $daysCount, 6500, 'daily_steps'),
                'hydration' => $this->calcConsist('Hydration', $hydration, $daysCount, 8, 'water_glasses'),
                'nutrition' => $this->calcConsist('Nutrition', $nutrition, $daysCount, 3, 'protein_servings')
            ]
        ]);
    }

    private function calcConsist($title, $logs, $days, $target, $col = 'sleep_hours')
    {
        $count = $logs->count();
        $avg = round($logs->avg($col) ?? 0, 1);
        return [
            'title' => $title,
            'avg' => "$avg avg this week",
            'percentage' => round(($count / $days) * 100),
            'status' => ($count >= ($days * 0.7)) ? "ON TRACK" : "Need Attention"
        ];
    }

    public function trainerOverview(Request $request)
    {
        try {
            $coachId = auth()->id();
            
            $todaysCheckinsCount = Schedule::where('trainer_id', $coachId)
                ->whereDate('schedule_date', now()->today())
                ->count();

            $totalSignups = User::where('user_type', 'individual')->orWhere('user_type', 'professional')->count();
            
            $clientsQuery = User::where('user_type', 'individual')
                ->whereHas('targetGoals', function($q) use ($coachId) {
                    $q->where('id', $coachId);
                });

            $activeCount = $totalSignups;
            
            $attentionCount = (clone $clientsQuery)->whereDoesntHave('activityLogs', function($q) {
                $q->where('log_date', '>=', now()->subDays(2));
            })->count();

            $clientsTable = (clone $clientsQuery)->with(['targetGoals', 'activityLogs' => function($q) {
                    $q->latest('log_date');
                }])
                ->get()
                ->map(function($user) {
                    $latestLog = $user->activityLogs->first();
                    $lastLogDate = $latestLog ? \Carbon\Carbon::parse($latestLog->log_date) : null;
                    $diff = $lastLogDate ? now()->diffInDays($lastLogDate) : null;

                    return [
                        'user_name'       => $user->name,
                        'goal'            => $user->targetGoals->goal_name ?? 'General wellness',
                        'projection_used' => '3/10',
                        'status'          => ($diff === null || $diff >= 3) ? 'Need attention' : 'On track',
                        'activity'        => $this->resolveActivityText($diff, $lastLogDate),
                        'user_id'         => $user->id
                    ];
                });

            return response()->json([
                'success' => true,
                'stats' => [
                    'active_clients'    => ['value' => $activeCount, 'label' => 'Currently coached'],
                    'needing_attention' => ['value' => $attentionCount, 'label' => 'Off-track or low activity'],
                    'pending_messages'  => ['value' => 3, 'label' => 'Unread client messages'], 
                    
                    'todays_checkins'   => ['value' => $todaysCheckinsCount, 'label' => 'Scheduled Today'] 
                ],
                'client_table' => $clientsTable,
                'today_actions' => [
                    ['title' => 'Review progress', 'desc' => 'Check recent updates', 'link' => '/admin/clients'],
                    ['title' => 'Send motivation', 'desc' => 'Sera encouragement or reminders', 'link' => '/admin/messages'],
                    ['title' => 'Review check-ins', 'desc' => 'View scheduled check-ins', 'link' => '/admin/calendar']
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function resolveActivityText($diff, $date)
    {
        if ($diff === null) return "Missed check-in";
        if ($diff === 0) return "Logged today";
        if ($diff < 3) return "Logged " . $date->diffForHumans();
        return "No log in $diff days";
    }
    

    public function connectToProfession(Request $request)
    {
        $request->validate([
            'profession_id' => 'required|exists:users,id',
        ]);

        try {
            $user = auth()->user();
            $professionId = $request->profession_id;

            // ২. নিজেকে কানেক্ট করা রোধ করা
            if ($user->id == $professionId) {
                return response()->json(['success' => false, 'message' => "You can't connect to yourself"], 400);
            }

            // ৩. কানেকশন তৈরি (ডুপ্লিকেট হবে না)
            $user->myProfessionals()->syncWithoutDetaching([$professionId]);

            // ৪. কানেক্টেড ইউজারের তথ্য তুলে আনা
            $connectedUser = \App\Models\User::with('profile')->find($professionId);

            return response()->json([
                'success' => true,
                'message' => 'Successfully connected to the professional',
                'data' => [
                    'connection_details' => [
                        'connected_at' => now()->format('Y-m-d H:i:s'),
                        'status'       => 'active'
                    ],
                    'professional_info' => [ // এখানে ইউজারের ফুল ইনফরমেশন
                        'id'            => $connectedUser->id,
                        'name'          => $connectedUser->name,
                        'email'         => $connectedUser->email,
                        'user_type'     => $connectedUser->user_type,
                        'bio'           => $connectedUser->profile?->bio ?? null,
                        'profile_image' => $connectedUser->profile?->image 
                                        ? asset('storage/' . $connectedUser->profile->image) 
                                        : null,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }   

    public function getMyConnections()
    {
        try {
            $user = auth()->user(); 

            if ($user->user_type === 'professional' || $user->user_type === 'trainer' || $user->user_type === 'coach') {
                
                $connections = $user->belongsToMany(User::class, 'connect_user_proffesions', 'profession_id', 'user_id')
                                    ->get(['users.id', 'users.name', 'users.email']); 
                $message = "Your connected clients";
            } 
            else {
                $connections = $user->belongsToMany(User::class, 'connect_user_proffesions', 'user_id', 'profession_id')
                                    ->get(['users.id', 'users.name', 'users.email']); 
                $message = "Professionals you are following";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'current_user' => [
                    'id' => $user->id,
                    'type' => $user->user_type
                ],
                'count' => $connections->count(),
                'data' => $connections
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

