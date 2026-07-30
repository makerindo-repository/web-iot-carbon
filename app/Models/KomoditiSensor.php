<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomoditiSensor extends Model
{
    protected $table = 'komoditi_sensor';

    protected $fillable = [
        'komoditi_id',
        'sensor_suhu', 'sensor_kelembapan_udara', 'sensor_kelembapan_tanah',
        'sensor_ph_tanah', 'sensor_npk', 'sensor_cahaya', 'sensor_co2',
        'sensor_curah_hujan', 'parameter_kritis',
    ];

    protected $casts = [
        'sensor_suhu' => 'boolean',
        'sensor_kelembapan_udara' => 'boolean',
        'sensor_kelembapan_tanah' => 'boolean',
        'sensor_ph_tanah' => 'boolean',
        'sensor_npk' => 'boolean',
        'sensor_cahaya' => 'boolean',
        'sensor_co2' => 'boolean',
        'sensor_curah_hujan' => 'boolean',
        'parameter_kritis' => 'array',
    ];

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }
}
