<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NguoiDung extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'nguoidung';

    protected $fillable = [
        'taiKhoanId',
        'cuaHangId',
        'hoTen',
        'soDienThoai',
        'gioiTinh', // 0: Nữ, 1: Nam, 2: Khác
        'ngaySinh',
        'cccdNumber',
        'cccdImageUrl',
        'loaiNguoiDung', // 0: Khách hàng, 1: Nhân viên
    ];

    protected $casts = [
        'gioiTinh' => 'integer',
        'loaiNguoiDung' => 'integer',
        'ngaySinh' => 'date',
    ];

    protected $appends = [
        'HOTEN',
        'SDT',
        'GioiTinh',
        'NGAYSINH',
        'DIACHI',
        'MAKH',
        'MANV',
        'DIEMTICHLUY',
    ];

    public function getHOTENAttribute()
    {
        return $this->attributes['hoTen'] ?? null;
    }

    public function getSDTAttribute()
    {
        return $this->attributes['soDienThoai'] ?? null;
    }

    public function getGioiTinhAttribute()
    {
        $val = $this->attributes['gioiTinh'] ?? null;
        if ((int)$val === 1) return 'Nam';
        if ((int)$val === 0 && !is_null($val)) return 'Nữ';
        return 'Khác';
    }

    public function getNGAYSINHAttribute()
    {
        $val = $this->attributes['ngaySinh'] ?? null;
        if (!$val) return null;
        return is_string($val) ? substr($val, 0, 10) : (method_exists($val, 'format') ? $val->format('Y-m-d') : (string)$val);
    }

    public function getDIACHIAttribute()
    {
        return $this->diachi()->first()?->diaChiChiTiet ?? '';
    }

    public function getMAKHAttribute()
    {
        return 'KH_' . sprintf('%05d', $this->attributes['id'] ?? $this->id ?? 0);
    }

    public function getMANVAttribute()
    {
        return 'NV_' . sprintf('%05d', $this->attributes['id'] ?? $this->id ?? 0);
    }

    public function getDIEMTICHLUYAttribute()
    {
        return $this->attributes['diem_tich_luy'] ?? 0;
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function diachi()
    {
        return $this->hasMany(DiaChi::class, 'nguoidungId');
    }

    public function donhang()
    {
        return $this->hasMany(DonHang::class, 'nguoidungId');
    }

    public function giohang()
    {
        return $this->hasOne(GioHang::class, 'nguoidungId');
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class, 'nguoidungId');
    }
}
