<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuGiaSanPham extends Model
{
    use HasFactory;

    protected $table = 'lich_su_gia_san_pham';

    protected $fillable = [
        'sanpham_id',
        'gia_cu',
        'gia_moi',
        'phan_tram_giam',
        'ly_do',
        'nguoidung_id',
        'ngay_ap_dung',
    ];

    protected $casts = [
        'gia_cu' => 'double',
        'gia_moi' => 'double',
        'phan_tram_giam' => 'double',
        'ngay_ap_dung' => 'datetime',
    ];

    protected $appends = ['MASP', 'GIA_CU', 'GIA_MOI', 'PHAN_TRAM_GIAM', 'LY_DO', 'NGAY_AP_DUNG'];

    public function getMASPAttribute()
    {
        return $this->attributes['sanpham_id'] ?? null;
    }

    public function getGIACUAttribute()
    {
        return $this->attributes['gia_cu'] ?? null;
    }

    public function getGIAMOIAttribute()
    {
        return $this->attributes['gia_moi'] ?? null;
    }

    public function getPHANTRAMGIAMAttribute()
    {
        return $this->attributes['phan_tram_giam'] ?? 0;
    }

    public function getLYDOAttribute()
    {
        return $this->attributes['ly_do'] ?? null;
    }

    public function getNGAYAPDUNGAttribute()
    {
        return $this->attributes['ngay_ap_dung'] ?? null;
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }

    public function nguoiDung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidung_id');
    }
}
