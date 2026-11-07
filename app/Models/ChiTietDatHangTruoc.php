<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietDatHangTruoc extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'chi_tiet_dat_hang_truoc';

    protected $fillable = [
        'MADHT',
        'MASP',
        'TEN_SP',
        'SO_LUONG',
        'DON_GIA',
        'GHI_CHU_SP',
    ];

    protected $casts = [
        'SO_LUONG' => 'integer',
        'DON_GIA'  => 'integer',
    ];

    // Thành tiền (computed)
    public function getThanhTienAttribute(): int
    {
        return $this->SO_LUONG * $this->DON_GIA;
    }

    public function datHangTruoc()
    {
        return $this->belongsTo(DatHangTruoc::class, 'MADHT', 'MADHT');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'MASP', 'MASP');
    }
}
