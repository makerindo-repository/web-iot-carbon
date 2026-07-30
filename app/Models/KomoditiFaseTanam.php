<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomoditiFaseTanam extends Model
{
    use HasFactory;

    protected $table = 'komoditi_fase_tanam';

    protected $fillable = [
        'komoditi_id',
        'usia_tanam_min',
        'usia_tanam_max',
        'satuan_usia',
        'fase_pembibitan',
        'fase_vegetatif',
        'fase_generatif',
        'fase_panen',
        'catatan_budidaya',
    ];

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }
}
