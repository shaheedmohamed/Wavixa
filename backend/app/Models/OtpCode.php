<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class OtpCode extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'code',
        'purpose',
        'verified_at',
        'expires_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Store OTP codes as bcrypt hashes instead of plaintext.
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Hash::make($value);
    }

    /**
     * Find a valid, unverified OTP and verify the supplied code against the hash.
     */
    public static function verify(int $userId, string $phone, string $code, ?string $purpose = null): ?self
    {
        $query = self::where('user_id', $userId)
            ->where('phone_number', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now());

        if ($purpose) {
            $query->where('purpose', $purpose);
        }

        foreach ($query->get() as $otp) {
            if (Hash::check($code, $otp->code)) {
                return $otp;
            }
        }

        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
