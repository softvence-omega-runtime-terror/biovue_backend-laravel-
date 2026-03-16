<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AI\ProjectionFutureGoal;

class ProjectionFutureGoalController extends Controller
{
    /**
     * Save AI future goal projection response
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {

            $data = $request->all();

            $projection = ProjectionFutureGoal::create([

                'user_id' => $data['user_id'],

                'image' => $data['image'] ?? null,
                'duration' => $data['duration'] ?? '1 year',
                'resolution' => $data['resolution'] ?? '2K',
                'tier' => $data['tier'] ?? 'ultra',

                'use_default_goal' => $data['use_default_goal'] ?? true,
                'goal' => $data['goal'] ?? null,
                'goal_description' => $data['goal_description'] ?? null,

                'projection_id' => $data['projection_id'] ?? null,
                'projection_url' => $data['projection_url'] ?? null,
                'route' => $data['route'] ?? null,
                'timeframe' => $data['timeframe'] ?? null,

                'est_bmi' => $data['est_bmi'] ?? null,
                'est_weight' => $data['est_weight'] ?? null,

                'expected_changes' => isset($data['expected_changes'])
                    ? json_encode($data['expected_changes'])
                    : null,

                'confidence_score' => $data['confidence_score'] ?? null,
            ]);

            return response()->json([
                'message' => 'Future goal projection saved successfully',
                'data' => $projection
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Show latest future goal projection
     */
    public function showLatest($user_id)
    {
        try {

            $projection = ProjectionFutureGoal::where('user_id', $user_id)
                ->latest()
                ->first();

            if (!$projection) {
                return response()->json([
                    'message' => 'No projection found for this user'
                ], 404);
            }

            return response()->json([
                'message' => 'Projection retrieved successfully',
                'data' => $projection
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}