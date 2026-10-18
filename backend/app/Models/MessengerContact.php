<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerContact extends Model
{
    protected $table = 'messenger_contacts';

    protected $fillable = [
        'user_id',
        'page_id',
        'psid',
        'name',
        'profile_pic',
        'first_contact_at',
        'last_message_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_contact_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MessengerPage::class, 'page_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(MessengerConversation::class, 'contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'psid', 'psid');
    }
}
