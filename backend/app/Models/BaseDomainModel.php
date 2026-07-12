<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseDomainModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'profile' => 'array',
        'items' => 'array',
        'value' => 'array',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'published_at' => 'date',
        'date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
