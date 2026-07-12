<?php

namespace App\Models;

class Payment extends BaseDomainModel
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
