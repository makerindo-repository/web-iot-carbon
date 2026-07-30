<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
    use HasFactory;

    protected $fillable = [
        'land_plot_id',
        'garden_code',
        'garden_name',
        'latitude',
        'longitude',
        'polygon',
        'area_hectare',
        'soil_type',
        'plant_types',
        'tanggal_tanam',
        'fase_tanam_saat_ini',
        'plant_id',
        'komoditi_id',
        'color',
        'keterangan',
        'kondisi_sekitar',
        'radius_konteks_m',
        'jarak_jalan_m',
    ];

    protected $casts = [
        'polygon' => 'json',
    ];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'land_plot_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(GardenActivityLog::class);
    }
}
