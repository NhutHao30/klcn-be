<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuaHang extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'cuahang';

    protected $fillable = [
        'tenCuaHang',
        'soDienThoai',
        'diaChiChiTiet',
        'maTinh',
        'maHuyen',
        'maXa',
        'ghnShopId',
        'trangThai',
    ];

    protected $appends = ['MACUAHANG', 'TENCUAHANG', 'DIACHI', 'SDT', 'name', 'address', 'phone'];

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENCUAHANGAttribute()
    {
        return $this->attributes['tenCuaHang'] ?? null;
    }

    public function getNameAttribute()
    {
        return $this->attributes['tenCuaHang'] ?? null;
    }

    public function getDIACHIAttribute()
    {
        return $this->attributes['diaChiChiTiet'] ?? $this->attributes['diaChi'] ?? '';
    }

    public function getAddressAttribute()
    {
        return $this->attributes['diaChiChiTiet'] ?? $this->attributes['diaChi'] ?? '';
    }

    public function getSDTAttribute()
    {
        return $this->attributes['soDienThoai'] ?? '';
    }

    public function getPhoneAttribute()
    {
        return $this->attributes['soDienThoai'] ?? '';
    }

    public function nguoiDungs()
    {
        return $this->hasMany(NguoiDung::class, 'cuaHangId');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'cuaHangId');
    }

    public function hoaDonNhaps()
    {
        return $this->hasMany(HoaDonNhap::class, 'cuaHangId');
    }

    public function bienBanHuys()
    {
        return $this->hasMany(BienBanHuy::class, 'cuaHangId');
    }
}
