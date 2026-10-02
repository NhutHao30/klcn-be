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
        'ten_cuahang',
        'soDienThoai',
        'diaChiChiTiet',
        'maTinh',
        'maHuyen',
        'maXa',
        'ghn_shop_id',
        'trangThai',
    ];

    protected $appends = ['MACUAHANG', 'TENCUAHANG'];

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENCUAHANGAttribute()
    {
        return $this->attributes['ten_cuahang'] ?? null;
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
