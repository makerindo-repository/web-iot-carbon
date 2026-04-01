<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilteredFixStation extends Model
{
    use HasFactory;
    protected $fillable = ['device_id', 'samples'];
    protected $casts = [
        'samples' => 'object',
    ];
}
