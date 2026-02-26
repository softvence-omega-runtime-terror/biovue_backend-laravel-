<?php

namespace App\Http\Controllers\SleepLog;

use App\Http\Controllers\Controller;
use App\Models\SleepLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SleepController extends Controller
{
    public function store(Request $request) 
    { 
        $validated = $request->validate([ 
            'user_id' => 'required|exists:users,id',
            'log_date' => 'required|date',
            'weight' => 'nullable|integer', 
            'daily_steps' => 'nullable|integer', 
            'sleep_hours' => 'nullable|numeric', 
            'water_glasses' => 'nullable|integer', 
        ]); 

        $activity = SleepLog::create($validated); 
        return response()->json($activity, 201);

    } 
    
    public function index()
    {
        return SleepLog::where('user_id', Auth::id())->get();
    }

    public function show($id)
    {
        return SleepLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail(); 
    }

    public function getSleepReport(Request $request)
    {
        $userId = auth()->id();
        $type = $request->query('type', 'weekly'); 
        
        $endDate = Carbon::today();
        $startDate = $this->getStartDate($type);

        $logs = SleepLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->orderBy('log_date', 'asc')
            ->get();

        $formattedLogs = $logs->keyBy(fn($item) => Carbon::parse($item->log_date)->format('Y-m-d'));

        $chartData = $this->generateSleepChartData($type, $startDate, $endDate, $formattedLogs, $userId);

        $avgSleep = $logs->avg('sleep_hours') ?? 0;
        $daysWithData = $logs->where('sleep_hours', '>', 0)->count();
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $consistency = ($daysWithData / $totalDays) * 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'chart_data' => $chartData,
                'statistics' => [
                    'average_sleep' => number_format($avgSleep, 1) . " Hrs",
                    'consistency' => round($consistency) . "%",
                    'best_streak' => $this->calculateStreak($userId) . " DAYS",
                    'current_trend' => $this->getTrendStatus($chartData),
                ],
                'bio_insight' => $this->generateBioInsight($consistency, $avgSleep)
            ]
        ]);
    }

    private function getStartDate($type) {
        return match($type) {
            'monthly' => Carbon::today()->subDays(29),
            '3_months' => Carbon::today()->subMonths(3),
            default => Carbon::today()->subDays(6),
        };
    }

    private function generateSleepChartData($type, $startDate, $endDate, $logs, $userId) {
        $data = [];
        if ($type === '3_months') {
            $period = \Carbon\CarbonPeriod::create($startDate, '1 week', $endDate);
            foreach ($period as $date) {
                $weekEnd = $date->copy()->addDays(6);
                $avg = SleepLog::where('user_id', $userId)
                        ->whereBetween('log_date', [$date, $weekEnd])
                        ->avg('sleep_hours') ?? 0;
                $data[] = ['label' => $date->format('M d'), 'hours' => (float)number_format($avg, 1)];
            }
        } else {
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $data[] = [
                    'label' => $type === 'monthly' ? $date->format('d M') : $date->format('D'),
                    'hours' => $logs->has($dateString) ? (float)$logs[$dateString]->sleep_hours : 0
                ];
            }
        }
        return $data;
    }

    private function calculateStreak($userId) {
        return SleepLog::where('user_id', $userId)->where('sleep_hours', '>=', 1)->count();
    }

    private function getTrendStatus($chartData) {
        $count = count($chartData);
        if ($count < 2) return "Stable";
        return $chartData[$count-1]['hours'] >= $chartData[$count-2]['hours'] ? "Improving" : "Declining";
    }

    private function generateBioInsight($consistency, $avg) {
        if ($consistency > 80 && $avg >= 7) return "Excellent! Your sleep cycle is very stable.";
        return "Your sleep consistency improved this weekly after Tuesday, maintaining regular timing will significantly increase your physical recovery markers.";
    }

}
