<?php

namespace App\Models;

class Attendance extends BaseDomainModel
{
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
