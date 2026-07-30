<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForecastPrediction extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'predicted_for' => 'datetime',
        'feature_snapshot' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function sourceReading()
    {
        return $this->belongsTo(IotReading::class, 'source_reading_id');
    }
}
