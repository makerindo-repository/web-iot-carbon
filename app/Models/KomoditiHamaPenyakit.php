<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomoditiHamaPenyakit extends Model
{
    protected $table = 'komoditi_hama_penyakit';

    protected $fillable = [
        'komoditi_id', 'nama', 'jenis', 'gejala', 'tingkat_risiko', 'pengendalian',
    ];

    public function komoditi()
    {
        return $this->belongsTo(KomoditiTanaman::class, 'komoditi_id');
    }
}
