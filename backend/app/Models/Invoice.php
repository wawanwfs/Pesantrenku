<?php

namespace App\Models;

class Invoice extends BaseDomainModel
{
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
