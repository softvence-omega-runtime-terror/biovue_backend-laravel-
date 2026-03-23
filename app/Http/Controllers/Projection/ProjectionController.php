<?php

namespace App\Http\Controllers\Projection;

use App\Http\Controllers\Controller;
use App\Models\Projection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProjectionController extends Controller
{
    public function generateProjection(Request $request) 
    {
        $user = auth()->user()->load('profile');
        
        $request->validate([
            'current_photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        try {
            $file = $request->file('current_photo');
            $path = $file->store('projections', 'public');

            $aiResponse = Http::timeout(120) 
                ->attach(
                    'image', 
                    file_get_contents($file), 
                    $file->getClientOriginalName()
                )
                ->post('https://ai.biovuedigitalwellness.com/api/v1/projection/current-lifestyle/', [
                    'user_id' => (string) $user->id,
                    'weight'  => $user->profile->weight ?? 0,
                    'height'  => $user->profile->height ?? 0,
                ]);

            if ($aiResponse->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI Service Error: ' . ($aiResponse->json()['detail'] ?? 'Unknown Error'),
                    'status'  => $aiResponse->status()
                ], $aiResponse->status());
            }

            $aiData = $aiResponse->json();

            $projection = \App\Models\Projection::create([
                'user_id'          => $user->id,
                'current_photo'    => $path,
                'projection_url'   => $aiData['projection_url'] ?? null,
                'projected_weight' => $aiData['est_weight'] ?? null,
                'projected_bmi'    => $aiData['est_bmi'] ?? null,
                'expected_changes' => json_encode($aiData['expected_changes'] ?? []),
                'confidence_score' => $aiData['confidence_score'] ?? null,
            ]);

            return response()->json([
                'user_id'          => $aiData['user_id'],
                'projection_id'    => $aiData['projection_id'],
                'projection_url'   => $aiData['projection_url'],
                'route'            => $aiData['route'],
                'timeframe'        => $aiData['timeframe'],
                'est_bmi'          => $aiData['est_bmi'],
                'est_weight'       => $aiData['est_weight'],
                'expected_changes' => $aiData['expected_changes'],
                'confidence_score' => $aiData['confidence_score']
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    // public function generateProjection(Request $request) 
    // {
    //     $user = Auth::user()->load('profile');
        
    //     $request->validate([
    //         'current_photo' => 'required|image',
    //     ]);

    //     $path = $request->file('current_photo')->store('projections', 'public');

    //     $currentWeight = $user->profile->weight; 
    //     $currentHeight = $user->profile->height;

    //     $projectedWeight = $currentWeight - 5; 
    //     $projectedBmi = $projectedWeight / (($currentHeight/100) ** 2);

    //     $projection = Projection::create([
    //         'user_id' => $user->id,
    //         'current_photo' => $path,
    //         'projected_weight' => $projectedWeight,
    //         'projected_bmi' => $projectedBmi,
    //         'expected_changes' => [
    //             'Improved weight control',
    //             'Reduced body fat percentage',
    //             'Better metabolic health'
    //         ]
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'current_data' => [
    //             'weight' => $currentWeight,
    //             'bmi' => ($currentWeight / (($currentHeight/100) ** 2))
    //         ],
    //         'projected_data' => $projection
    //     ]);
    // }
}
