<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'individual_plan_id',
        'professional_plan_id',
        'payment_method',
        'transaction_id',
        'amount',
        'currency',
        'platform',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'paid_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Who made the payment
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // If payment is for Individual Plan
    public function individualPlan()
    {
        return $this->belongsTo(IndividualPlan::class, 'individual_plan_id');
    }

    // If payment is for Professional Plan
    public function professionalPlan()
    {
        return $this->belongsTo(ProfessionalPlan::class, 'professional_plan_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper (optional but very useful)
    |--------------------------------------------------------------------------
    */

    // Detect plan type automatically
    public function getPlanTypeAttribute()
    {
        if ($this->individual_plan_id) {
            return 'individual';
        }

        if ($this->professional_plan_id) {
            return 'professional';
        }

        return null;
    }
}