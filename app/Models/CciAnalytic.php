<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CciAnalytic extends Model
{
    use HasFactory;

    protected $table = 'cci_analytics';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'cci_value' => 'decimal:3',
    ];

    public function iotReading()
    {
        return $this->belongsTo(IotReading::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_code');
    }
}
