<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuGiaSanPham extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'lichSuGiaSanPham';

    protected $fillable = [
        'sanPhamId',
        'gia_cu',
        'gia_moi',
        'phan_tram_giam',
        'ly_do',
        'nguoidungId',
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
        return $this->attributes['sanPhamId'] ?? null;
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
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }

    public function nguoiDung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }
}
