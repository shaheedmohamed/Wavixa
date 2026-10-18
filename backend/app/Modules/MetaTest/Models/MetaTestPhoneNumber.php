<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaTestPhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'meta_test_phone_numbers';

    protected $fillable = [
        'user_id',
        'waba_id',
        'meta_phone_number_id',
        'phone_number',
        'display_name',
        'quality_rating',
        'status',
        'messaging_limit_tier',
        'platform_type',
        'account_mode',
        'is_active',
        'raw_data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(MetaTestWaba::class, 'waba_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MetaTestMessage::class, 'phone_number_id');
    }
}
