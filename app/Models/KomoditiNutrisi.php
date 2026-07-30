<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomoditiNutrisi extends Model
{
    use HasFactory;

    protected $fillable = [
        'komoditi_id', 'nitrogen_min', 'nitrogen_max', 'fosfor_min', 'fosfor_max',
        'kalium_min', 'kalium_max', 'satuan_npk', 'bahan_organik_min', 'bahan_organik_max',
        'rekomendasi_pemupukan',
    ];
}
