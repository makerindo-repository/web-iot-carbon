<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarbonDailyStock extends Model
{
    protected $fillable = [
        'device_id',
        'stock_date',
        'daily_gpp_gc_m2',
        'daily_reco_gc_m2',
        'daily_npp_gc_m2',
        'daily_nee_gc_m2',
        'cumulative_npp_gc_m2',
        'readings_count',
    ];

    protected $casts = [
        'stock_date' => 'date',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
