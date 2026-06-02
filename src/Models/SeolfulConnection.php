<?php

namespace Seolful\Connector\Models;

use Illuminate\Database\Eloquent\Model;

class SeolfulConnection extends Model
{
    protected $table = 'seolful_connection';

    protected $fillable = [
        'client_id',
        'token_hash',
        'site_url',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];
}
