<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'idempotencykeys';

    protected $fillable = [
        'idempotencyKey',
        'requestPath',
        'requestHash',
        'responsePayload',
        'statusCode',
        'expiresAt',
    ];

    protected $casts = [
        'expiresAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}

