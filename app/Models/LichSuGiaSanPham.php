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
        'giaCu',
        'giaMoi',
        'phanTramGiam',
        'lyDo',
        'nguoidungId',
        'ngayApDung',
    ];

    protected $casts = [
        'giaCu' => 'double',
        'giaMoi' => 'double',
        'phanTramGiam' => 'double',
        'ngayApDung' => 'datetime',
    ];

    protected $appends = ['MASP', 'GIA_CU', 'GIA_MOI', 'PHAN_TRAM_GIAM', 'LY_DO', 'NGAY_AP_DUNG'];

    public function getMASPAttribute()
    {
        return $this->attributes['sanPhamId'] ?? null;
    }

    public function getGIACUAttribute()
    {
        return $this->attributes['giaCu'] ?? null;
    }

    public function getGIAMOIAttribute()
    {
        return $this->attributes['giaMoi'] ?? null;
    }

    public function getPHANTRAMGIAMAttribute()
    {
        return $this->attributes['phanTramGiam'] ?? 0;
    }

    public function getLYDOAttribute()
    {
        return $this->attributes['lyDo'] ?? null;
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
