<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    protected $fillable = ['category', 'name', 'description'];

    public function gardens()
    {
        return $this->hasMany(Garden::class);
    }
}
