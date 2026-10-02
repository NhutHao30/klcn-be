<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamCong extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'chamcong';

    protected $fillable = [
        'USERNAME',
        'NGAYCHAMCONG',
        'TRANGTHAI',
    ];

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'username');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, 'USERNAME', 'taiKhoanId');
    }
}
