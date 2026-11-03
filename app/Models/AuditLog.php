<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'auditlogs';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'taiKhoanId',
        'action',
        'tableName',
        'oldValues',
        'newValues',
        'ipAddress',
        'userAgent',
    ];

    protected $casts = [
        'oldValues' => 'array',
        'newValues' => 'array',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $appends = [
        'user',
        'USERNAME',
        'entity_type',
        'entity_id',
        'ip_address',
        'old_values',
        'new_values',
        'created_at',
        'description',
        'request_id',
        'role_name',
    ];

    public function getUserAttribute()
    {
        $tk = $this->taikhoan;
        return [
            'HOTEN' => $tk?->nguoidung?->hoTen ?? $tk?->username ?? 'Hệ thống',
            'USERNAME' => $tk?->username ?? 'Hệ thống',
        ];
    }

    public function getUSERNAMEAttribute()
    {
        return $this->taikhoan?->username ?? 'Hệ thống';
    }

    public function getEntityTypeAttribute()
    {
        return $this->tableName;
    }

    public function getEntityIdAttribute()
    {
        if (!empty($this->attributes['entityId'])) {
            return $this->attributes['entityId'];
        }
        $newVals = is_array($this->newValues) ? $this->newValues : json_decode($this->newValues ?? '[]', true);
        $oldVals = is_array($this->oldValues) ? $this->oldValues : json_decode($this->oldValues ?? '[]', true);
        return $newVals['id'] ?? $oldVals['id'] ?? $this->id;
    }

    public function getDescriptionAttribute()
    {
        return $this->attributes['moTa'] ?? null;
    }

    public function getRequestIdAttribute()
    {
        return $this->attributes['requestId'] ?? null;
    }

    public function getRoleNameAttribute()
    {
        return $this->taikhoan?->vaiTro?->tenVaiTro ?? null;
    }

    public function getIpAddressAttribute()
    {
        return $this->attributes['ipAddress'] ?? '127.0.0.1';
    }

    public function getOldValuesAttribute()
    {
        $val = $this->attributes['oldValues'] ?? null;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            return $decoded !== null ? $decoded : $val;
        }
        return $val;
    }

    public function getNewValuesAttribute()
    {
        $val = $this->attributes['newValues'] ?? null;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            return $decoded !== null ? $decoded : $val;
        }
        return $val;
    }

    public function getCreatedAtAttribute()
    {
        return $this->attributes['createdAt'] ?? now();
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }

    public function user()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }
}
