<?php

namespace App\Http\Controllers\StressLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StressReportController extends Controller
{
    public function getStressReport(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->format('Y-m-d');
            $sevenDaysAgo = now()->subDays(6)->format('Y-m-d');

            $days = collect(range(6, 0))->map(function($i) {
                return now()->subDays($i)->format('D'); 
            });

            $stressLogs = DB::table('stress_logs')
                ->where('user_id', $user->id)
                ->whereBetween('log_date', [$sevenDaysAgo, $today])
                ->get()
                ->keyBy(function($item) {
                    return Carbon::parse($item->log_date)->format('D');
                });

            $chartValues = $days->map(function($day) use ($stressLogs) {
                return $stressLogs->has($day) ? $stressLogs[$day]->stress_level : 0;
            });

            $avgStress = DB::table('stress_logs')
                ->where('user_id', $user->id)
                ->avg('stress_level') ?? 0;

            $logCount = DB::table('stress_logs')
                ->where('user_id', $user->id)
                ->whereBetween('log_date', [$sevenDaysAgo, $today])
                ->count();
            $consistency = round(($logCount / 7) * 100);

            $streak = $this->calculateStreak($user->id);

            return response()->json([
                'success' => true,
                'stress_progress' => [
                    'labels' => $days,
                    'values' => $chartValues,
                ],
                'stats' => [
                    'average' => round($avgStress, 1) . '/10 PTS',
                    'consistency' => $consistency . '%',
                    'best_streak' => $streak . ' DAYS',
                    'current_trend' => $avgStress < 3 ? 'Improving' : 'Stable',
                ],
                
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function calculateStreak($userId) 
    {
        $logs = DB::table('stress_logs')
            ->where('user_id', $userId)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        $streak = 0;
        $currentDate = now()->startOfDay();

        foreach ($logs as $logDate) {
            $date = Carbon::parse($logDate)->startOfDay();
            if ($date->equalTo($currentDate)) {
                $streak++;
                $currentDate->subDay();
            } elseif ($date->lessThan($currentDate)) {
                break;
            }
        }
        return $streak;
    }
}
