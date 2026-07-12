<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('tenant.{tenantId}.user.{userId}', function ($user, string $tenantId, int $userId) {
    if ((int) $user->id !== $userId) {
        return false;
    }

    if ($user->hasRole('super_admin')) {
        return true;
    }

    return DB::table('pesantren_user')
        ->where('pesantren_id', (int) $tenantId)
        ->where('user_id', $userId)
        ->where('status', 'active')
        ->exists();
});
