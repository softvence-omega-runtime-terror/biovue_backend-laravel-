<?php

namespace App\Http\Controllers\HydrationLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HydrationReportController extends Controller
{
    public function getHydrationReport(Request $request)
    {
        try {
            $user = $request->user();
            $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('D'));
            $startDate = now()->subDays(6)->format('Y-m-d');
            $endDate = now()->format('Y-m-d');

            $hydrationData = DB::table('hydration_logs')
                ->where('user_id', $user->id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->get()->keyBy(fn($item) => \Carbon\Carbon::parse($item->log_date)->format('D'));

            $hydrationChart = $days->mapWithKeys(fn($day) => [
                $day => $hydrationData->get($day)->water_glasses ?? 0
            ]);

            $avgWater = DB::table('hydration_logs')->where('user_id', $user->id)->avg('water_glasses') ?? 0;
            
            $totalLogs = DB::table('hydration_logs')
                ->where('user_id', $user->id)
                ->whereBetween('log_date', [$startDate, $endDate])
                ->count();
            
            $consistency = round(($totalLogs / 7) * 100);

            $currentTrend = ($avgWater >= 6) ? 'Improving' : 'Stable';

            return response()->json([
                'success' => true,
                'hydration' => [
                    'chart' => $hydrationChart, 
                    'average' => round($avgWater, 1) . ' Glasses GLS',
                    'best_streak' => $this->calculateStreak($user->id, 'hydration_logs') . ' DAYS',
                    'consistency' => $consistency . '%',
                    'current_trend' => $currentTrend 
                ],
               
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function calculateStreak($userId, $tableName)
    {
        $dates = DB::table($tableName)
            ->where('user_id', $userId)
            ->orderBy('log_date', 'desc')
            ->pluck('log_date');

        if ($dates->isEmpty()) return 0;

        $streak = 0;
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        
        $lastLogDate = \Carbon\Carbon::parse($dates[0])->startOfDay();

        if (!$lastLogDate->equalTo($today) && !$lastLogDate->equalTo($yesterday)) {
            return 0;
        }

        $compareDate = $lastLogDate; 

        foreach ($dates as $date) {
            $logDate = \Carbon\Carbon::parse($date)->startOfDay();
            
            if ($logDate->equalTo($compareDate)) {
                $streak++;
                $compareDate->subDay();
            } elseif ($logDate->greaterThan($compareDate)) {
                continue;
            } else {
                break;
            }
        }
        return $streak;
    }
}
