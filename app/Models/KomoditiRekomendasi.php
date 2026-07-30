<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomoditiRekomendasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'komoditi_id', 'status_kesesuaian_lahan', 'skor_kesesuaian', 'kategori_rekomendasi',
        'parameter_bermasalah', 'rekomendasi_tindakan', 'prioritas_tindakan', 'catatan_rekomendasi',
    ];
}
