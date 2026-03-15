<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetGoal extends Model
{
    protected $fillable = [
        'user_id', 'target_weight', 'weekly_workout_goal', 
        'daily_step_goal', 'sleep_target', 'water_target', 'is_active', 
        'start_date', 'end_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
