<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planting extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_tanaman',
        'garden_id',
        'device_id',
        'komoditi_id',
        'tanggal_tanam',
        'estimasi_panen',
        'status_fase',
        'is_active',
    ];

    protected $casts = [
        'tanggal_tanam' => 'date',
        'estimasi_panen' => 'date',
        'is_active' => 'boolean',
    ];

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }
}
