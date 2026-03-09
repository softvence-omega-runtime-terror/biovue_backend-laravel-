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
        // Validate input
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,avif,webp|max:2048',
            'duration' => 'nullable|in:6 months,1 year,5 years',
            'resolution' => 'nullable|in:2K,4K',
            'tier' => 'nullable|in:ultra,fast',
        ]);

        try {
            $user = auth()->user();

            // Upload image to storage
            $imagePath = $request->file('image')->store('projection_images', 'public');
            $imageUrl = asset('storage/' . $imagePath);

            // Call external API with a longer timeout
            $response = Http::timeout(120) // wait up to 120 seconds
                ->withOptions(['verify' => false]) // ignore SSL issues if needed
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

            // Check if API call failed
            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Projection API failed',
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // Build full projection URL
            $projectionUrl = isset($data['projection_url'])
                ? 'https://biovue-ai.onrender.com' . $data['projection_url']
                : null;

            // Save data to database
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
                'expected_changes' => $data['expected_changes'] ?? null,
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


 /**
     * Show the latest projection of the authenticated user
     */
    public function showLatest()
    {
        try {
            $user = auth()->user();

            // Get the latest projection for this user
            $projection = ProjectionLifestyle::where('user_id', $user->id)
                ->latest() // orders by created_at descending
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