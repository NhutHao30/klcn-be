<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BinhLuanDanhGia extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'binhluan_danhgia';

    protected $fillable = [
        'danhGiaId',
        'nguoidungId',
        'taiKhoanId',
        'noiDung',
        'parentId',
        'trangThai',
    ];

    public function danhGia()
    {
        return $this->belongsTo(DanhGiaSanPham::class, 'danhGiaId');
    }

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }
}

