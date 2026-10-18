<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_connection_id',
        'meta_page_id',
        'name',
        'access_token',
        'category',
        'instagram_actor_id',
        'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaConnection::class, 'meta_connection_id');
    }
}
