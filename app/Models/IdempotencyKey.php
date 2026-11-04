<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'idempotencyKeys';

    public function getTable()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('idempotencyKeys')) {
                return 'idempotencyKeys';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('idempotencykeys')) {
                return 'idempotencykeys';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('idempotency_keys')) {
                return 'idempotency_keys';
            }
        } catch (\Throwable $e) {}
        return 'idempotencyKeys';
    }

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

