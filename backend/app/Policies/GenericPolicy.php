<?php

namespace App\Policies;

use App\Models\User;

class GenericPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function mutate(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin_keuangan', 'admin_akademik', 'admin_tu', 'guru', 'musyrif', 'klinik']);
    }
}
