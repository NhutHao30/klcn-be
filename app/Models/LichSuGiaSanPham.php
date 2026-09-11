<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LichSuGiaSanPham extends Model
{
    protected $table = 'lich_su_gia_san_phams';

    protected $fillable = [
        'MASP',
        'GIA_CU',
        'GIA_MOI',
        'PHAN_TRAM_GIAM',
        'LY_DO',
        'USERNAME_NV',
        'NGAY_AP_DUNG',
    ];

    protected $casts = [
        'GIA_CU' => 'decimal:2',
        'GIA_MOI' => 'decimal:2',
        'PHAN_TRAM_GIAM' => 'float',
        'NGAY_AP_DUNG' => 'datetime',
    ];

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'MASP', 'MASP');
    }

    public function nhanvien()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME_NV', 'USERNAME');
    }
}
