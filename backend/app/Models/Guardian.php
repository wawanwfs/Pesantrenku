<?php

namespace App\Models;

class Guardian extends BaseDomainModel
{
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
