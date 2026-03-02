<?php

namespace App\Http\Controllers\Projection;

use App\Http\Controllers\Controller;
use App\Models\Projection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectionController extends Controller
{
    public function generateProjection(Request $request) 
    {
        $user = Auth::user()->load('profile');
        
        $request->validate([
            'current_photo' => 'required|image',
            'time_horizon' => 'required|string'
        ]);

        $path = $request->file('current_photo')->store('projections', 'public');

        $currentWeight = $user->profile->weight; 
        $currentHeight = $user->profile->height;

        $projectedWeight = $currentWeight - 5; 
        $projectedBmi = $projectedWeight / (($currentHeight/100) ** 2);

        $projection = Projection::create([
            'user_id' => $user->id,
            'current_photo' => $path,
            'time_horizon' => $request->time_horizon,
            'projected_weight' => $projectedWeight,
            'projected_bmi' => $projectedBmi,
            'expected_changes' => [
                'Improved weight control',
                'Reduced body fat percentage',
                'Better metabolic health'
            ]
        ]);

        return response()->json([
            'success' => true,
            'current_data' => [
                'weight' => $currentWeight,
                'bmi' => ($currentWeight / (($currentHeight/100) ** 2))
            ],
            'projected_data' => $projection
        ]);
    }
}
