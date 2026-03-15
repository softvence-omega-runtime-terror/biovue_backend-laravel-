<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\AI\ProjectionLifestyle;

class ProjectionLifestyleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'image' => 'required|file|mimes:jpg,jpeg,png,avif,webp|max:5120',
            'duration' => 'nullable|in:6 months,1 year,5 years',
            'resolution' => 'nullable|in:2K,4K',
            'tier' => 'nullable|in:ultra,fast',
        ]);

        try {

            $userId = $request->user_id;

            /*
            |--------------------------------------------------------------------------
            | Upload image locally
            |--------------------------------------------------------------------------
            */

            $imageFile = $request->file('image');

            $imagePath = $imageFile->store('projection_images', 'public');

            $imageUrl = asset('storage/' . $imagePath);

            /*
            |--------------------------------------------------------------------------
            | Call AI Projection API
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
                    'https://biovue-ai.onrender.com/api/v1/projection/current-lifestyle/',
                    [
                        'user_id' => $userId,
                        'duration' => $request->duration ?? '1 year',
                        'resolution' => $request->resolution ?? '2K',
                        'tier' => $request->tier ?? 'ultra',
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
            | Save projection to database
            |--------------------------------------------------------------------------
            */

            $projection = ProjectionLifestyle::create([
                'user_id' => $userId,
                'image' => $imageUrl,
                'duration' => $request->duration ?? '1 year',
                'resolution' => $request->resolution ?? '2K',
                'tier' => $request->tier ?? 'ultra',
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
                'message' => 'Projection generated successfully',
                'data' => $projection
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function showLatest($userId)
    {
        try {

            $projection = ProjectionLifestyle::where('user_id', $userId)
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