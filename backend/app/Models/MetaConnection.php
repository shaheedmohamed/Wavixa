<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'app_id',
        'access_token',
        'token_expires_at',
        'is_active',
        'status',
        'scopes',
        'error_message',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'scopes' => 'array',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(MetaAdAccount::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(MetaPage::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(MetaPixel::class);
    }
}
