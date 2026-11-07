<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = true;

    protected $table = 'idempotencyKeys';

    protected $guarded = [];

    public function getTable()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('idempotencyKeys')) {
                return 'idempotencyKeys';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('idempotency_keys')) {
                return 'idempotency_keys';
            }
        } catch (\Throwable $e) {}
        return 'idempotencyKeys';
    }
}
