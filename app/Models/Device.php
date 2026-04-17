<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function landPlot()
    {
        return $this->belongsTo(LandPlot::class, 'plot_id');
    }

    public function garden()
    {
        return $this->belongsTo(Garden::class, 'garden_id');
    }

    public function iotReadings()
    {
        return $this->hasMany(IotReading::class);
    }
}
