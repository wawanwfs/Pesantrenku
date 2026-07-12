<?php

namespace App\Models;

class Pesantren extends BaseDomainModel
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'pesantren_user')
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }
}
