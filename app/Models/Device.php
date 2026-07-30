<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    /**
     * Field yang aman di-set dari payload klien (NodeController store/update).
     *
     * Field internal yang DIKECUALIKAN dari mass-assign:
     * - device_status, last_seen_at: hanya boleh di-set dari service code
     *   (IotReadingController saat ingestion, atau scheduled job)
     * - id, created_at, updated_at: dikelola Eloquent
     */
    protected $fillable = [
        'device_code',
        'name',
        'location',
        'latitude',
        'longitude',
        'altitude',
        'plot_id',
        'garden_id',
        'firmware_version',
    ];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'plot_id');
    }

    public function garden()
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function iotReadings()
    {
        return $this->hasMany(IotReading::class);
    }
}
