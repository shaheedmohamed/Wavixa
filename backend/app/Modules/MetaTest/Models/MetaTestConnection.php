<?php

namespace App\Modules\MetaTest\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MetaTestConnection extends Model
{
    use HasFactory;

    protected $table = 'meta_test_connections';

    protected $fillable = [
        'user_id',
        'business_id',
        'business_name',
        'waba_id',
        'connection_type',
        'onboarding_type',
        'signup_event',
        'session_info',
        'access_token',
        'token_expires_at',
        'webhook_subscribed_at',
        'scopes',
        'status',
        'error_message',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'webhook_subscribed_at' => 'datetime',
        'scopes' => 'array',
        'session_info' => 'array',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_FAILED = 'failed';

    public const CONNECTION_TYPE_META_CLOUD_API = 'meta_cloud_api';

    // How the customer completed onboarding. Derived only from the
    // WA_EMBEDDED_SIGNUP session event reported by Meta — never guessed.
    public const ONBOARDING_CLOUD_API = 'cloud_api';
    public const ONBOARDING_BUSINESS_APP = 'business_app_onboarding'; // a.k.a. Coexistence
    public const ONBOARDING_WABA_ONLY = 'waba_only';
    public const ONBOARDING_OBO_MIGRATION = 'obo_migration';
    public const ONBOARDING_GRANT_ONLY = 'grant_only_api_access';
    public const ONBOARDING_OAUTH_REDIRECT = 'oauth_redirect';
    public const ONBOARDING_UNKNOWN = 'unknown';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wabas(): HasMany
    {
        return $this->hasMany(MetaTestWaba::class, 'connection_id');
    }

    public function phoneNumbers(): HasManyThrough
    {
        return $this->hasManyThrough(MetaTestPhoneNumber::class, MetaTestWaba::class, 'connection_id', 'waba_id');
    }

    public function isTokenExpired(): bool
    {
        if (empty($this->token_expires_at)) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function maskedToken(): ?string
    {
        if (empty($this->access_token)) {
            return null;
        }

        $len = strlen($this->access_token);

        return substr($this->access_token, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($this->access_token, -4);
    }
}
