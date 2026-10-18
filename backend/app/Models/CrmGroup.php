<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmGroup extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'session_ids',
    ];

    protected function casts(): array
    {
        return [
            'session_ids' => 'array',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agents()
    {
        return $this->hasMany(User::class, 'crm_group_id');
    }
}
