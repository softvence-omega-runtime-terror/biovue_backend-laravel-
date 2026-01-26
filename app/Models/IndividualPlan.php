<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'billing_cycle',
        'duration',
        'price',
        'status',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'status' => 'boolean',
    ];
}
