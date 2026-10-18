<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'avatar',
        'password',
        'is_admin',
        'status',
        'banned_at',
        'messages_balance',
        'country',
        'city',
        'last_ip',
        'last_device',
        'last_browser',
        'last_os',
        'last_activity_at',
        'language',
        'timezone',
        'notification_preferences',
        'subscription_plan',
        'subscription_status',
        'last_login_at',
        'onboarding_completed',
        'onboarding_step',
        'onboarding_completed_at',
        'reputation_score',
        'risk_level',
        'reputation_factors',
        'reputation_calculated_at',
        'crm_role',
        'crm_parent_id',
        'crm_assigned_sessions',
        'crm_group_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'banned_at' => 'datetime',
            'notification_preferences' => 'array',
            'is_admin' => 'boolean',
            'messages_balance' => 'integer',
            'onboarding_completed' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'reputation_score' => 'integer',
            'reputation_factors' => 'array',
            'reputation_calculated_at' => 'datetime',
            'crm_assigned_sessions' => 'array',
        ];
    }

    /**
     * Get user's API keys
     */
    public function apiKeys()
    {
        return $this->hasMany(\App\Models\ApiKey::class);
    }

    /**
     * CRM team group the agent belongs to
     */
    public function crmGroup()
    {
        return $this->belongsTo(\App\Models\CrmGroup::class, 'crm_group_id');
    }

    /**
     * Get user's WhatsApp sessions
     */
    public function whatsappSessions()
    {
        return $this->hasMany(\App\Models\WhatsAppSession::class);
    }

    /**
     * Get user's subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get user's active subscription
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at');
    }

    /**
     * Get user's transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get user's invoices
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get user's login history
     */
    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Get user's devices
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Get user's WhatsApp messages
     */
    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function messengerPages()
    {
        return $this->hasMany(MessengerPage::class);
    }

    public function adminRoles()
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user');
    }

    public function fraudAlerts()
    {
        return $this->hasMany(FraudAlert::class);
    }

    public function autoReply()
    {
        return $this->hasOne(WhatsAppAutoReply::class);
    }
}
