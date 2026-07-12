<?php

namespace App\Models;

class Student extends BaseDomainModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }
}
