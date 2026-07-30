<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomoditiLingkungan extends Model
{
    protected $table = 'komoditi_lingkungan';

    protected $fillable = [
        'komoditi_id', 'suhu_min', 'suhu_max',
        'kelembapan_udara_min', 'kelembapan_udara_max',
        'kelembapan_tanah_min', 'kelembapan_tanah_max',
        'ph_min', 'ph_max', 'intensitas_cahaya',
        'curah_hujan_min', 'curah_hujan_max',
        'ketinggian_min', 'ketinggian_max',
        'jenis_tanah', 'drainase', 'catatan',
    ];

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }
}
