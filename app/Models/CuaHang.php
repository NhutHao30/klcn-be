<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuaHang extends Model
{
    use HasFactory;

    protected $table = 'cuahang';

    protected $fillable = [
        'ten_cuahang',
        'so_dien_thoai',
        'dia_chi_chi_tiet',
        'ma_tinh',
        'ma_huyen',
        'ma_xa',
        'ghn_shop_id',
        'trang_thai',
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
        return $this->hasMany(NguoiDung::class, 'cuahang_id');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'cuahang_id');
    }

    public function hoaDonNhaps()
    {
        return $this->hasMany(HoaDonNhap::class, 'cuahang_id');
    }

    public function bienBanHuys()
    {
        return $this->hasMany(BienBanHuy::class, 'cuahang_id');
    }
}
