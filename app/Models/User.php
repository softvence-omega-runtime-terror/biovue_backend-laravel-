<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'otp',
        'otp_expire_at',
        'terms_accepted',
        'status',
        'plan_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }



   
    public function profile() 
    { 
        return $this->hasOne(UserProfile::class); 
    } 
    
    public function activityLogs() 
    { 
        return $this->hasMany(ActivityLog::class); 
    } 

    public function hydrationLogs() 
    { 
        return $this->hasMany(HydrationLog::class); 
    } 

    public function sleepLogs() 
    { 
        return $this->hasMany(SleepLog::class); 
    } 

    public function stressLogs() 
    { 
        return $this->hasMany(StressLog::class); 
    } 

    public function nutritionLogs() 
    { 
        return $this->hasMany(NutritionLog::class);
    } 

    public function targetGoals()
    { 
        return $this->hasOne(TargetGoal::class);
    } 

    public function adjustProgram()
    { 
        return $this->hasOne(AdjustProgram::class); 
    }

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }
    


    public function programSets()
    {
        return $this->belongsToMany(ProgramSet::class, 'connect_to_professions');
    }


    public function planPayments()
    {
        return $this->hasMany(PlanPayment::class, 'user_id');
    }





//for auto updated user plan id 
    public function plan()
    {
        return $this->belongsTo(\App\Models\Plan::class, 'plan_id');
    }
}
