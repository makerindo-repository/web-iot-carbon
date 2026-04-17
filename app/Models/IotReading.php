<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotReading extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'reading_time' => 'datetime',
        'data_valid' => 'boolean',
        'samples' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'plot_id');
    }

    public function cciAnalytic()
    {
        return $this->hasOne(CciAnalytic::class);
    }
}
