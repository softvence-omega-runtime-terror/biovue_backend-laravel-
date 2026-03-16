<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AI\ProjectionLifestyle;

class ProjectionLifestyleController extends Controller
{
    /**
     * Save AI projection response
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'image' => 'required|string',
            'duration' => 'nullable|in:6 months,1 year,5 years',
            'resolution' => 'nullable|in:2K,4K',
            'tier' => 'nullable|in:ultra,fast',

            'projection_id' => 'nullable|string',
            'projection_url' => 'nullable|string',
            'route' => 'nullable|string',
            'timeframe' => 'nullable|string',
            'est_bmi' => 'nullable|numeric',
            'est_weight' => 'nullable|numeric',
            'expected_changes' => 'nullable|array',
            'confidence_score' => 'nullable|numeric',
        ]);

        try {

            $projection = ProjectionLifestyle::create([
                'user_id' => $request->user_id,
                'image' => $request->image,
                'duration' => $request->duration ?? '1 year',
                'resolution' => $request->resolution ?? '2K',
                'tier' => $request->tier ?? 'ultra',

                'projection_id' => $request->projection_id,
                'projection_url' => $request->projection_url,
                'route' => $request->route,
                'timeframe' => $request->timeframe,
                'est_bmi' => $request->est_bmi,
                'est_weight' => $request->est_weight,
                'expected_changes' => $request->expected_changes
                    ? json_encode($request->expected_changes)
                    : null,
                'confidence_score' => $request->confidence_score,
            ]);

            return response()->json([
                'message' => 'Projection saved successfully',
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
     * Show latest projection by user
     */
    public function showLatest($user_id)
    {
        try {

            $projection = ProjectionLifestyle::where('user_id', $user_id)
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