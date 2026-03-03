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
}