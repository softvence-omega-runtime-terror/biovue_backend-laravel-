<?php

namespace App\Http\Controllers\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index()
    {
        return ActivityLog::where('user_id', Auth::id())
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request) 
    { 
        $validated = $request->validate([ 
            'id'            => 'nullable|exists:activity_logs,id',
            'log_date'      => 'required|date',
            'weight'        => 'nullable|numeric|min:0', 
            'daily_steps'   => 'nullable|integer|min:0', 
            'sleep_hours'   => 'nullable|numeric|min:0|max:24', 
            'water_glasses' => 'nullable|integer|min:0', 
        ]); 

        $activity = ActivityLog::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'id' => $request->id ?? null, 
                'log_date' => $validated['log_date']
            ], 
            $validated
        ); 

        $status = $activity->wasRecentlyCreated ? 201 : 200;
        $message = $activity->wasRecentlyCreated ? 'Activity log created.' : 'Activity log updated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $activity
        ], $status);
    }

    public function show($id)
    {
        $log = ActivityLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ], 200);
    }

    public function destroy($id)
    {
        $log = ActivityLog::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log successfully deleted'
        ], 200);
    }

    public function getActivityReport(Request $request)
    {
        $userId = auth()->id();
        $type = $request->query('type', 'weekly'); 
        
        $endDate = Carbon::today();
        $startDate = Carbon::today();

        if ($type == 'monthly') {
            $startDate = Carbon::today()->subDays(29);
        } elseif ($type == '3_months') {
            $startDate = Carbon::today()->subMonths(3);
        } else {
            $startDate = Carbon::today()->subDays(6);
        }

        $logs = ActivityLog::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->orderBy('log_date', 'asc')
            ->get()
            ->keyBy(fn($item) => Carbon::parse($item->log_date)->format('Y-m-d'));

        $chartData = [];
        $daysWithData = 0;

        if ($type == '3_months') {
            for ($date = $startDate->copy(); $date <= $endDate; $date->addWeek()) {
                $weekEnd = $date->copy()->addDays(6);
                $avgSteps = ActivityLog::where('user_id', $userId)
                    ->whereBetween('log_date', [$date, $weekEnd])
                    ->avg('daily_steps') ?? 0;

                $chartData[] = [
                    'label' => $date->format('M d'),
                    'steps' => (int)$avgSteps
                ];
            }
        } else {
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateString = $date->format('Y-m-d');
                $steps = isset($logs[$dateString]) ? $logs[$dateString]->daily_steps : 0;
                
                $chartData[] = [
                    'label' => $type == 'monthly' ? $date->format('d M') : $date->format('D'),
                    'steps' => $steps
                ];

                if($steps > 0) $daysWithData++;
            }
        }

        $totalDaysInRange = $startDate->diffInDays($endDate) + 1;
        $averageSteps = $logs->avg('daily_steps') ?? 0;
        $consistencyScore = ($daysWithData / $totalDaysInRange) * 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'chart_data' => $chartData,
                'statistics' => [
                    'average_steps' => number_format(round($averageSteps)) . " Steps",
                    'consistency' => round($consistencyScore) . "%",
                    'best_streak' => $this->calculateStreak($userId) . " DAYS", 
                    'current_trend' => $this->getTrendStatus($chartData),   
                ]
            ]
        ]);
    }


    private function calculateStreak($userId) {
        return ActivityLog::where('user_id', $userId)
            ->where('daily_steps', '>', 0)
            ->count(); 
    }

    private function getTrendStatus($chartData) {
        $count = count($chartData);
        if ($count < 2) return "Stable";
        
        $last = $chartData[$count - 1]['steps'];
        $previous = $chartData[$count - 2]['steps'];
        
        return $last >= $previous ? "Improving" : "Declining";
    }
}