<?php

namespace App\Models;

class AcademicClass extends BaseDomainModel
{
    protected $table = 'classes';

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}
