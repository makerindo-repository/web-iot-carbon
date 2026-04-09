<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandPlot extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // Relasi Data Garden
    public function gardens()
    {
        return $this->hasMany(Garden::class, 'land_plot_id');
    }
    // Relasi Data BMKG
    public function bmkgReadings()
    {
        return $this->hasMany(BmkgReading::class, 'plot_id');
    }

}
