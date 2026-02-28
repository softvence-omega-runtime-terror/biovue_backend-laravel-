<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plan_type',
        'user_id',
        'billing_cycle',
        'price',
        'duration',
        'member_limit',
        'features',
        'status',
    ];

    protected $casts = [
        'features' => 'array',       // JSON column automatically array hisebe handle korbe
        'status'   => 'boolean',
        'price'    => 'decimal:2',   // decimal precision 2
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Plan creator (admin / owner)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Future subscription relation
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}