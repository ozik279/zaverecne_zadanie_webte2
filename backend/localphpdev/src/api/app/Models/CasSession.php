<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasSession extends Model
{
    protected $fillable = [
        'client_token',
        'history',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'history' => 'array',
            'last_used_at' => 'datetime',
        ];
    }
}
