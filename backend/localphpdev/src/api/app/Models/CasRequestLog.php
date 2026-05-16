<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CasRequestLog extends Model
{
    protected $fillable = [
        'client_token',
        'source',
        'command',
        'successful',
        'stdout',
        'stderr',
        'error_message',
        'execution_ms',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'execution_ms' => 'integer',
        ];
    }
}
