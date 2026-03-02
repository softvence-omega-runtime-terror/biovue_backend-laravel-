<?php

namespace App\Http\Controllers\TargetGoal;
use App\Http\Controllers\Controller;

use App\Models\TargetGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TargetGoalController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $authUserId = Auth::id(); 
        $authUser = Auth::user();

        if ($authUser->user_type !== 'professional' || $authUser->profession_type !== 'trainer_coach') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only trainer coaches can set goals for clients.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'             => 'required|exists:users,id',
            'target_weight'       => 'nullable|numeric|between:0,999.99',
            'weekly_workout_goal' => 'nullable|integer|min:1|max:7',
            'daily_step_goal'     => 'nullable|integer|min:0',
            'sleep_target'        => 'nullable|numeric|between:0,24',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
        ]);

        $goal = TargetGoal::updateOrCreate(
            ['user_id' => $validated['user_id']],
            array_merge($validated, ['is_active' => true])
        );

        $client = \App\Models\User::find($validated['user_id']);
        $client->sendUserNotify('goal_updates', [
            'title' => 'New Goal Set',
            'message' => 'Your coach has updated your fitness targets.',
            'url' => '/goals'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Target goals updated successfully for the client',
            'data'    => $goal
        ]);
    }

    public function getGoal()
    {
        $goal = TargetGoal::where('user_id', Auth::id())->where('is_active', true)->first();

        return response()->json([
            'success' => true,
            'data'    => $goal
        ]);
    }
}
