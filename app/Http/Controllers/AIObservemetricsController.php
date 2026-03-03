<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AIObservemetricsController extends Controller
{
    /**
     * Show latest AI Observemetrics for logged-in user
     */
    public function show()
    {
        $user = auth()->user(); // logged-in user

        // Latest logs
        $latestActivity = $user->activityLogs()->latest('log_date')->first();
        $latestNutrition = $user->nutritionLogs()->latest('log_date')->first();
        $latestStress = $user->stressLogs()->latest('log_date')->first();

        // Nutrition adherence calculation
        $nutritionAdherence = null;
        if ($latestNutrition) {
            $totalServings = $latestNutrition->protein_servings + $latestNutrition->vegetable_servings;
            $nutritionAdherence = round(($totalServings / 10) * 100, 0);
        }

        // JSON data
        $data = [
            'weight' => [
                'value' => $latestActivity->weight ?? null,
                'unit' => 'lbs',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'sleep_average' => [
                'value' => $latestActivity->sleep_hours ?? null,
                'unit' => 'Hrs',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'activity_level' => [
                'value' => ($latestActivity->daily_steps ?? 0) >= 10000 ? 'High' : 'Moderate',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
            'nutrition_adherence' => [
                'value' => $nutritionAdherence,
                'unit' => '%',
                'updated_at' => $latestNutrition?->updated_at?->diffForHumans(),
            ],
            'stress_level' => [
                'value' => $latestStress->stress_level ?? 'Low',
                'updated_at' => $latestStress?->updated_at?->diffForHumans(),
            ],
            'water_intake' => [
                'value' => $latestActivity->water_glasses ?? 0,
                'unit' => 'L/day',
                'updated_at' => $latestActivity?->updated_at?->diffForHumans(),
            ],
        ];

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'data' => $data
        ]);
    }






    public function index()
{
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Latest records
        $activity = $user->activityLogs()->latest('log_date')->first();
        $nutrition = $user->nutritionLogs()->latest('log_date')->first();
        $stress = $user->stressLogs()->latest('log_date')->first();
        $hydration = $user->hydrationLogs()->latest('log_date')->first();

        // =========================
        // Calculations
        // =========================

        // Weight
        $weight = $activity->weight ?? null;

        // Nutrition %
        $nutritionQuality = null;
        if ($nutrition) {
            $total = $nutrition->protein_servings + $nutrition->vegetable_servings;
            $nutritionQuality = round(($total / 10) * 100);
        }

        // Steps
        $steps = $activity->daily_steps ?? 0;

        // Sleep format
        $sleepFormatted = null;
        if ($activity && $activity->sleep_hours) {
            $hours = floor($activity->sleep_hours);
            $minutes = round(($activity->sleep_hours - $hours) * 60);
            $sleepFormatted = $hours . 'h ' . $minutes . 'm';
        }

        // Stress label
        $stressLabel = null;
        if ($stress && $stress->stress_level) {
            if ($stress->stress_level >= 4) {
                $stressLabel = 'High';
            } elseif ($stress->stress_level >= 2) {
                $stressLabel = 'Moderate';
            } else {
                $stressLabel = 'Low';
            }
        }

        // Hydration
        $hydrationOz = null;
        if ($hydration) {
            $hydrationOz = $hydration->water_glasses * 8;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'weight' => $weight ? $weight . ' lbs' : null,
                'nutrition_quality' => $nutritionQuality ? $nutritionQuality . '%' : null,
                'steps' => $steps,
                'sleep' => $sleepFormatted,
                'stress' => $stressLabel,
                'hydration' => $hydrationOz ? $hydrationOz . ' oz' : null,
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}
}