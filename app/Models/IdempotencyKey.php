<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false; // Only created_at

    protected $fillable = [
        'idempotency_key',
        'USERNAME',
        'endpoint',
        'request_hash',
        'status',
        'response_code',
        'response_body',
        'resource_type',
        'resource_id',
        'expires_at',
        'created_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'USERNAME');
    }
}
