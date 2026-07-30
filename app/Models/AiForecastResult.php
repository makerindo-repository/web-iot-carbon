<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiForecastResult extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'predicted_for' => 'datetime',
        'input_reading_time' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
