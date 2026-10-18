<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class N8nTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'template_json',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
