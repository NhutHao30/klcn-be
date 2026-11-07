<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatHangTruoc extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'dat_hang_truoc';
    protected $primaryKey = 'MADHT';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MADHT',
        'MACUAHANG',
        'USERNAME_NV',
        'MAKH',
        'HOTEN_KH',
        'SDT_KH',
        'NGAY_DAT',
        'NGAY_HEN_LAY',
        'GHI_CHU',
        'TONG_TIEN',
        'TIEN_COC',
        'PHUONG_THUC_COC',
        'LY_DO_DAT_TRUOC',
        'HINH_THUC_NHAN',
        'DIA_CHI_GIAO',
        'PHI_GIAO_HANG',
        'TRANG_THAI',
        'MAHD_HOAN_THANH',
        'CHU_KY',
    ];

    protected $casts = [
        'NGAY_DAT'      => 'datetime',
        'NGAY_HEN_LAY'  => 'datetime',
        'TONG_TIEN'     => 'integer',
        'TIEN_COC'      => 'integer',
        'PHI_GIAO_HANG' => 'integer',
    ];

    // Tiền còn lại (computed)
    public function getTienConLaiAttribute(): int
    {
        return max(0, $this->TONG_TIEN - $this->TIEN_COC);
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietDatHangTruoc::class, 'MADHT', 'MADHT');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'MACUAHANG', 'MACUAHANG');
    }

    public function hdBanHoanThanh()
    {
        return $this->belongsTo(HdBan::class, 'MAHD_HOAN_THANH', 'MAHD');
    }
}
