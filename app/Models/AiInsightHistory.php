<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInsightHistory extends Model
{
    protected $fillable = [
        'device_id',
        'time_range',
        'provider',
        'analysis_text',
        'recommendation_json',
    ];

    protected $casts = [
        // 'recommendation_json' => 'array',
    ];
}
