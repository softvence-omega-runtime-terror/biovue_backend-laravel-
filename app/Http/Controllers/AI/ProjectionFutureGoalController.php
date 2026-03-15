<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\ProjectionFutureGoal;

class ProjectionFutureGoalController extends Controller
{

    /**
     * Store future goal projection using the external API
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'image' => 'required|file|mimes:jpg,jpeg,png,avif,webp|max:5120',
            'duration' => 'nullable|in:6 months,1 year,5 years',
            'resolution' => 'nullable|in:2K,4K',
            'tier' => 'nullable|in:ultra,fast',
            'use_default_goal' => 'boolean',
            'goal' => 'nullable|string',
            'goal_description' => 'nullable|string',
        ]);

        try {

            $userId = $request->user_id;

            /*
            |--------------------------------------------------------------------------
            | Upload image locally
            |--------------------------------------------------------------------------
            */

            $imageFile = $request->file('image');

            $imagePath = $imageFile->store('projection_future_goal_images', 'public');

            $imageUrl = asset('storage/' . $imagePath);


            /*
            |--------------------------------------------------------------------------
            | Call External AI API
            |--------------------------------------------------------------------------
            */

            $response = Http::retry(3, 5000)
                ->timeout(600)
                ->attach(
                    'image',
                    file_get_contents($imageFile->getRealPath()),
                    $imageFile->getClientOriginalName()
                )
                ->post(
                    'https://biovue-ai.onrender.com/api/v1/projection/future-goal/',
                    [
                        'user_id' => $userId,
                        'duration' => $request->duration ?? '1 year',
                        'resolution' => $request->resolution ?? '2K',
                        'tier' => $request->tier ?? 'ultra',
                        'use_default_goal' => $request->use_default_goal ?? true,
                        'goal' => $request->goal,
                        'goal_description' => $request->goal_description,
                    ]
                );

            if (!$response->successful()) {

                return response()->json([
                    'message' => 'Projection API failed',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();


            /*
            |--------------------------------------------------------------------------
            | Build projection URL
            |--------------------------------------------------------------------------
            */

            $projectionUrl = isset($data['projection_url'])
                ? 'https://biovue-ai.onrender.com' . $data['projection_url']
                : null;


            /*
            |--------------------------------------------------------------------------
            | Save projection in database
            |--------------------------------------------------------------------------
            */

            $projection = ProjectionFutureGoal::create([

                'user_id' => $userId,
                'image' => $imageUrl,
                'duration' => $request->duration ?? '1 year',
                'resolution' => $request->resolution ?? '2K',
                'tier' => $request->tier ?? 'ultra',

                'use_default_goal' => $request->use_default_goal ?? true,
                'goal' => $request->goal,
                'goal_description' => $request->goal_description,

                'projection_id' => $data['projection_id'] ?? null,
                'projection_url' => $projectionUrl,
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
                'message' => 'Future goal projection generated successfully',
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