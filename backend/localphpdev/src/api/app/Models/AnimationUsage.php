<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnimationUsage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'simulation',
        'client_token',
        'ip_address',
        'city',
        'country',
    ];
}