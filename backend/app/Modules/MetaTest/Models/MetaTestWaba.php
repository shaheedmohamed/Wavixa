<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaTestWaba extends Model
{
    use HasFactory;

    protected $table = 'meta_test_wabas';

    protected $fillable = [
        'user_id',
        'connection_id',
        'meta_waba_id',
        'name',
        'timezone',
        'currency',
        'status',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaTestConnection::class, 'connection_id');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(MetaTestPhoneNumber::class, 'waba_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MetaTestTemplate::class, 'waba_id');
    }
}
