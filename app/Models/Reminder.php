<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = ['sender_id', 'client_id', 'reminder_type', 'message', 'in_app', 'push_notification'];
}
