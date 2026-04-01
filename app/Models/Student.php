<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function activitySchedules()
    {
        return $this->belongsToMany(ActivitySchedule::class, 'activity_schedules_students');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
