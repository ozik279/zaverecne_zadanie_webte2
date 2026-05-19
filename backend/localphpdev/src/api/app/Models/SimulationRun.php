<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'simulation',
        'client_token',
        'request_payload',
        'result_payload',
        'successful',
        'duration_ms',
        'ip_address',
        'city',
        'country',
        'error_message',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'request_payload' => 'array',
        'result_payload' => 'array',
        'successful' => 'boolean',
    ];
}