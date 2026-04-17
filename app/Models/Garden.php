<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
    use HasFactory;
    protected $fillable = [
        'land_plot_id',
        'garden_code',
        'garden_name',
        'latitude',
        'longitude',
        'polygon',
        'area_hectare',
        'soil_type',
        'plant_types'
    ];
    protected $casts = [
        'polygon' => 'json',
    ];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'land_plot_id');
    }
}
