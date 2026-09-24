<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BienBanHuy extends Model
{
    use HasFactory;

    protected $table = 'bien_ban_huy';

    protected $fillable = [
        'ma_bien_ban',
        'cuahang_id',
        'nguoi_lap_id',
        'ngay_lap',
        'ly_do',
        'tong_gia_tri_huy',
        'trang_thai',
    ];

    protected $casts = [
        'ngay_lap' => 'datetime',
        'tong_gia_tri_huy' => 'double',
    ];

    protected $appends = [
        'MABIENBAN', 'MABBHUY', 'MACUAHANG', 'NGAY_HUY', 'NGAYLAP',
        'LYDO_HUY', 'LYDO', 'TONGGIATRI_HUY', 'TRANGTHAI'
    ];

    public function getMABIENBANAttribute()
    {
        return $this->attributes['ma_bien_ban'] ?? $this->attributes['id'] ?? null;
    }

    public function getMABBHUYAttribute()
    {
        return $this->attributes['ma_bien_ban'] ?? $this->attributes['id'] ?? null;
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuahang_id'] ?? null;
    }

    public function getNGAYHUYAttribute()
    {
        return $this->attributes['ngay_lap'] ?? null;
    }

    public function getNGAYLAPAttribute()
    {
        return $this->attributes['ngay_lap'] ?? null;
    }

    public function getLYDOHUYAttribute()
    {
        return $this->attributes['ly_do'] ?? null;
    }

    public function getLYDOAttribute()
    {
        return $this->attributes['ly_do'] ?? null;
    }

    public function getTONGGIATRIHUYAttribute()
    {
        return $this->attributes['tong_gia_tri_huy'] ?? 0;
    }

    public function getTRANGTHAIAttribute()
    {
        return $this->attributes['trang_thai'] ?? 0;
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_id');
    }

    public function nguoiLap()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoi_lap_id');
    }

    public function chuKySos()
    {
        return $this->hasMany(ChuKySo::class, 'tai_lieu_id')->where('loai_tai_lieu', 2);
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietBienBanHuy::class, 'bien_ban_huy_id');
    }

    /**
     * Biên bản hủy yêu cầu đủ 2 chữ ký: Kế toán (1), Thủ kho (2).
     */
    public function kiemTraDaKyDu(): bool
    {
        $signedRolesCount = $this->chuKySos()->where('trang_thai', 1)->distinct('vai_tro_ky')->count('vai_tro_ky');
        return $signedRolesCount >= 2;
    }
}
