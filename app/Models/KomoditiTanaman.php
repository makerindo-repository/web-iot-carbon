<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomoditiTanaman extends Model
{
    protected $table = 'komoditi_tanaman';

    protected $fillable = [
        'kode_komoditi',
        'nama_komoditi',
        'kategori_tanaman',
        'nama_latin',
        'varietas',
        'deskripsi',
        'status',
        'fapar',
        'epsilon_max',
        'is_system',
        'foto',
    ];

    protected $casts = [
        'fapar' => 'decimal:3',
        'epsilon_max' => 'decimal:3',
        'is_system' => 'boolean',
    ];

    public function lingkungan()
    {
        return $this->hasOne(KomoditiLingkungan::class, 'komoditi_id');
    }

    public function hamaPenyakit()
    {
        return $this->hasMany(KomoditiHamaPenyakit::class, 'komoditi_id');
    }

    public function sensor()
    {
        return $this->hasOne(KomoditiSensor::class, 'komoditi_id');
    }

    public function gardens()
    {
        return $this->hasMany(Garden::class, 'komoditi_id');
    }

    public function faseTanam()
    {
        return $this->hasOne(KomoditiFaseTanam::class, 'komoditi_id');
    }

    public function nutrisi()
    {
        return $this->hasOne(KomoditiNutrisi::class, 'komoditi_id');
    }

    public function rekomendasi()
    {
        return $this->hasOne(KomoditiRekomendasi::class, 'komoditi_id');
    }
}
