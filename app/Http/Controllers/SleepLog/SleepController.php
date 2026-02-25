<?php

namespace App\Http\Controllers\SleepLog;

use App\Http\Controllers\Controller;
use App\Models\SleepLog;
use Illuminate\Http\Request;

class SleepController extends Controller
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

        $activity = SleepLog::create($validated); 
        return response()->json($activity, 201);

    } 
    
    public function index($user_id) 
    { 
        return SleepLog::where('user_id', $user_id)->get(); 
    }
}
