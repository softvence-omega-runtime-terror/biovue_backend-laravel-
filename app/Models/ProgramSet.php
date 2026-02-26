<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramSet extends Model
{
    use HasFactory, SoftDeletes; // Soft delete support

    protected $table = 'programs_sets';

    // Mass assignable fields
    protected $fillable = [
        'name',
        'duration',
        'primary_goal',
        'target_intensity',
        'habit_focus_areas',
        'program_focus',
        'focus_areas',
        'habit_focus',
        'calories',
        'protein',
        'carbs',
        'fat',
        'supplement_recommendation',
        'supplement',
    ];

    // Cast JSON columns to array automatically
    protected $casts = [
        'habit_focus_areas' => 'array',
        'program_focus' => 'array',
        'focus_areas' => 'array',
        'habit_focus' => 'array',
        'supplement_recommendation' => 'array',
        'supplement' => 'array',
    ];
}