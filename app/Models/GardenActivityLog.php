<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GardenActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'garden_id',
        'tanggal',
        'jenis_aktivitas',
        'keterangan',
        'user_id',
    ];

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
