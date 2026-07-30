<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutCard extends Model
{
    protected $fillable = [
        'type', 'title', 'subtitle', 'description',
        'image_url', 'link', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
