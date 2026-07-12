<?php

namespace App\Models;

class Grade extends BaseDomainModel
{
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
