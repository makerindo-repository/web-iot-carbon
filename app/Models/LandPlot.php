<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandPlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'plot_code',
        'plot_name',
        'owner_name',
        'address',
        'latitude',
        'longitude',
        'area_hectare',
        'soc_baseline_gc_m2',
        'c_max_gc_m2',
        'soc_source',
        'plant_types',
        'polygon',
        'color',
        'keterangan',
    ];

    // prote
    protected $casts = [
        'polygon' => 'json',
    ];

    // Relasi Data Garden
    public function gardens()
    {
        return $this->hasMany(Garden::class, 'land_plot_id');
    }

    // Relasi Data BMKG
    public function bmkgReadings()
    {
        return $this->hasMany(BmkgReading::class, 'plot_id');
    }

    public function iotReadings()
    {
        return $this->hasMany(IotReading::class, 'plot_id');
    }
}
