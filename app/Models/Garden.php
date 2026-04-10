<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // Polygon berbentuk json
    protected $casts = [
        'polygon' => 'json',
    ];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'land_plot_id');
    }
}
