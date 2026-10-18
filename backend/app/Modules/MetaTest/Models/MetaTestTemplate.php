<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaTestTemplate extends Model
{
    use HasFactory;

    protected $table = 'meta_test_templates';

    protected $fillable = [
        'user_id',
        'waba_id',
        'meta_template_id',
        'name',
        'category',
        'language',
        'status',
        'components',
        'raw_data',
    ];

    protected $casts = [
        'components' => 'array',
        'raw_data' => 'array',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PAUSED = 'PAUSED';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waba(): BelongsTo
    {
        return $this->belongsTo(MetaTestWaba::class, 'waba_id');
    }
}
