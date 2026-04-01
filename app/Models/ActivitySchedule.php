<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivitySchedule extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function lecturers()
    {
        return $this->belongsToMany(Lecturer::class, 'activity_schedules_lecturers');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'activity_schedules_students');
    }

    public function scopeForCurrentUser($query)
    {
        $user = Auth::user();

        if($user->role == 'mahasiswa' && $user->student){
            return $query->whereHas('students', function($q) use ($user){
                $q->where('student_id', $user->student->id);
            });
        }
        
        if($user->role == 'dosen' && $user->lecturer){
            return $query->whereHas('lecturers', function($q) use ($user){
                $q->where('lecturer_id', $user->lecturer->id);
            });
        }

        return $query;
    }
}
