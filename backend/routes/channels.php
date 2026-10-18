<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// CRM realtime channel — scoped to the account owner so the owner and every
// agent working under them share one live feed. Agents resolve to their
// parent's owner id via crm_parent_id.
Broadcast::channel('crm.{ownerId}', function (User $user, int $ownerId) {
    $resolved = $user->crm_role === 'agent' && $user->crm_parent_id
        ? (int) $user->crm_parent_id
        : (int) $user->id;

    return $resolved === (int) $ownerId;
});
