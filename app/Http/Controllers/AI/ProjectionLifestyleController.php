<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\AI\ProjectionLifestyle;
use App\Models\Projection;
use Illuminate\Support\Str;

class ProjectionLifestyleController extends Controller
{
    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,avif,webp|max:5120',
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
            $response = Http::timeout(300) // wait up to 120 seconds
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

    public function generateProjection(Request $request)
{
    // ১. মেথডের শুরুতেই পিএইচপি এক্সিকিউশন টাইম বাড়িয়ে নিন
    set_time_limit(300); 

    $request->validate([
        'image'      => 'required|image|mimes:jpeg,png,jpg|max:10240', 
        'duration'   => 'required|in:6 months,1 year,5 years',
        'resolution' => 'nullable|in:2K,4K',
        'tier'       => 'nullable|in:ultra,fast',
    ]);

    try {
        $user = auth()->user();
        $apiUrl = "https://biovue-ai.onrender.com/api/v1/projection/current-lifestyle/";

        $currentPhotoPath = $request->file('image')->store('projections/originals', 'public');

        // ২. এখানে Timeout এবং connectTimeout যোগ করুন
        $response = Http::timeout(180) // মোট ১৮০ সেকেন্ড পর্যন্ত উত্তরের জন্য অপেক্ষা করবে
            ->connectTimeout(30)       // সার্ভারের সাথে কানেক্ট হতে ৩০ সেকেন্ড সময় নিবে
            ->attach(
                'image', 
                file_get_contents($request->file('image')), 
                $request->file('image')->getClientOriginalName()
            )->post($apiUrl, [
                'user_id'    => (string) $user->id,
                'duration'   => $request->duration,
                'resolution' => $request->input('resolution', '2K'),
                'tier'       => $request->input('tier', 'fast'),
            ]);

        if ($response->successful()) {
            $apiData = $response->json();
            $projectedImageUrl = "https://biovue-ai.onrender.com" . $apiData['projection_url'];

            // ৩. এখানে file_get_contents এর বদলে Http::get ব্যবহার করুন টাইমআউটসহ
            $imageResponse = Http::timeout(120)->get($projectedImageUrl);
            
            if(!$imageResponse->successful()) {
                throw new \Exception("Failed to download projected image from AI server.");
            }
            
            $imageContent = $imageResponse->body();
            $projectedFileName = 'projections/results/' . Str::random(30) . '.webp';
            Storage::disk('public')->put($projectedFileName, $imageContent);

            // ডাটাবেসে সেভ করা
            $projection = Projection::create([
                'user_id'          => $user->id,
                'projection_id'    => $apiData['projection_id'],
                'lifestyle_type'   => $apiData['route'] ?? 'current-lifestyle',
                'time_horizon'     => $apiData['timeframe'],
                'duration'         => $request->duration,  
                'resolution'       => $request->input('resolution', '2K'),
                'tier'             => $request->input('tier', 'fast'),
                'current_photo'    => $currentPhotoPath,
                'projected_photo'  => $projectedFileName,
                'projected_bmi'    => $apiData['est_bmi'],
                'projected_weight' => (float) filter_var($apiData['est_weight'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                'expected_changes' => $apiData['expected_changes'], 
                'confidence_score' => $apiData['confidence_score'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Future projection generated successfully!',
                'data'    => $projection
            ], 201);
        }

        return response()->json([
            'success' => false, 
            'error'   => 'AI Service Provider Error',
            'details' => $response->json()
        ], $response->status());

    } catch (\Exception $e) {
        return response()->json([
            'success' => false, 
            'error'   => 'Server Error: ' . $e->getMessage()
        ], 500);
    }
}
}