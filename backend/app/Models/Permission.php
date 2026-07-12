<?php

namespace App\Models;

class Permission extends BaseDomainModel
{
    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }
}
