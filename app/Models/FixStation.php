<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixStation extends Model
{
    use HasFactory;

    protected $fillable = ['device_id', 'samples'];

    protected $casts = [
        'samples' => 'object',
    ];
}
