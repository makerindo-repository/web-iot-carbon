<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BmkgReading extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'plot_id');
    }

    // Hubungan spesifik jika station_id atau area dikonfirmasi ke model lain
    // bisa ditambahkan kelak
}
