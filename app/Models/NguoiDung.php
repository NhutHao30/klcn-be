<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NguoiDung extends Model
{
    use HasFactory;

    protected $table = 'nguoidung';

    protected $fillable = [
        'taikhoan_id',
        'cuahang_id',
        'ho_ten',
        'so_dien_thoai',
        'gioi_tinh', // 0: Nữ, 1: Nam, 2: Khác
        'ngay_sinh',
        'cccd_number',
        'cccd_image_url',
        'loai_nguoidung', // 0: Khách hàng, 1: Nhân viên
    ];

    protected $casts = [
        'gioi_tinh' => 'integer',
        'loai_nguoidung' => 'integer',
        'ngay_sinh' => 'date',
    ];

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taikhoan_id');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_id');
    }

    public function diachi()
    {
        return $this->hasMany(DiaChi::class, 'nguoidung_id');
    }

    public function donhang()
    {
        return $this->hasMany(DonHang::class, 'nguoidung_id');
    }

    public function giohang()
    {
        return $this->hasOne(GioHang::class, 'nguoidung_id');
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class, 'nguoidung_id');
    }
}
