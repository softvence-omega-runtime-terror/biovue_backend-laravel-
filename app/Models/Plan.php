<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'plan_type', 'user_id', 'billing_cycle', 
        'price', 'duration', 'member_limit', 'features', 'status'
    ];

    protected $casts = [
        'features' => 'array',
        'status' => 'boolean',
    ];
}
