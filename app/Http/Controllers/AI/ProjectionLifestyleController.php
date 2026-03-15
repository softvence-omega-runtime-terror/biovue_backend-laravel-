<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\AI\ProjectionLifestyle;

class ProjectionLifestyleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,avif,webp|max:5120',
            'duration' => 'nullable|in:6 months,1 year,5 years',
            'resolution' => 'nullable|in:2K,4K',
            'tier' => 'nullable|in:ultra,fast',
        ]);

        try {

            $user = auth()->user();
            $profile = $user->profile;

            if (!$profile) {
                return response()->json([
                    'message' => 'User profile not found'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 1 : Sync profile with AI server
            |--------------------------------------------------------------------------
            */

            Http::timeout(300)
                ->withOptions(['verify' => false])
                ->post('https://biovue-ai.onrender.com/api/v1/profile/', [
                    'user_id' => $user->id,
                    'age' => $profile->age,
                    'sex' => $profile->sex,
                    'height' => $profile->height,
                    'weight' => $profile->weight,
                    'location' => $profile->location,
                ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 2 : Upload image locally
            |--------------------------------------------------------------------------
            */

            $imagePath = $request->file('image')->store('projection_images', 'public');
            $imageUrl = asset('storage/' . $imagePath);

            /*
            |--------------------------------------------------------------------------
            | STEP 3 : Call Projection API
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(300)
                ->withOptions(['verify' => false])
                ->attach(
                    'image',
                    file_get_contents($request->file('image')->getRealPath()),
                    $request->file('image')->getClientOriginalName()
                )
                ->post('https://biovue-ai.onrender.com/api/v1/projection/current-lifestyle/', [
                    'user_id' => $user->id,
                    'duration' => $request->duration ?? '1 year',
                    'resolution' => $request->resolution ?? '2K',
                    'tier' => $request->tier ?? 'ultra',
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Projection API failed',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            $projectionUrl = isset($data['projection_url'])
                ? 'https://biovue-ai.onrender.com' . $data['projection_url']
                : null;

            /*
            |--------------------------------------------------------------------------
            | STEP 4 : Save projection to database
            |--------------------------------------------------------------------------
            */

            $projection = ProjectionLifestyle::create([
                'user_id' => $user->id,
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
                'expected_changes' => json_encode($data['expected_changes'] ?? []),
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


    /*
    |--------------------------------------------------------------------------
    | Get latest projection
    |--------------------------------------------------------------------------
    */

    public function showLatest()
    {
        try {

            $user = auth()->user();

            $projection = ProjectionLifestyle::where('user_id', $user->id)
                ->latest()
                ->first();

            if (!$projection) {
                return response()->json([
                    'message' => 'No projection found'
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