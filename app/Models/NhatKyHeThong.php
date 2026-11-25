<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhatKyHeThong extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'nhatKyHeThong';

    protected $fillable = [
        'USERNAME',
        'HOTEN',
        'MAROLE',
        'MACUAHANG',
        'HANH_DONG',
        'CHITIET',
        'IP_ADDRESS',
    ];

    /**
     * Quan hệ với bảng tài khoản
     */
    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'USERNAME');
    }
}
