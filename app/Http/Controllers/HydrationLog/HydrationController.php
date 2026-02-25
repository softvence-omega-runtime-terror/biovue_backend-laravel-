<?php

namespace App\Http\Controllers\HydrationLog;

use App\Http\Controllers\Controller;
use App\Models\HydrationLog;
use Illuminate\Http\Request;

class HydrationController extends Controller
{
    public function store(Request $request) 
    { 
        $validated = $request->validate([ 
            'user_id' => 'required|exists:users,id',
            'log_date' => 'required|date', 
            'weight' => 'nullable|integer', 
            'daily_steps' => 'nullable|integer', 
            'sleep_hours' => 'nullable|numeric', 
            'water_glasses' => 'nullable|integer', 
        ]); 

        $activity = HydrationLog::create($validated); 
        return response()->json($activity, 201);

    } 
    
    public function index($user_id) 
    { 
        return HydrationLog::where('user_id', $user_id)->get(); 
    }
}
