<?php

namespace App\Http\Controllers\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityController extends Controller
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

        $activity = ActivityLog::create($validated); 
        return response()->json($activity, 201);

    } 

    public function index($user_id) 
    { 
        return ActivityLog::where('user_id', $user_id)->get(); 
    }
}
