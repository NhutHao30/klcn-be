<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhuyenMaiKhachHang extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'khuyenmai_khachhang';

    protected $fillable = [
        'MA_VOUCHER',
        'MAKH',
        'MAHD',
        'NGAY_THU_THAP',
        'NGAY_SU_DUNG',
        'TRANG_THAI',
    ];

    protected $casts = [
        'NGAY_THU_THAP' => 'datetime',
        'NGAY_SU_DUNG' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(KhuyenMai::class, 'MA_VOUCHER', 'MA_VOUCHER');
    }

    public function khachhang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }

    public function scopeChuaSuDung($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('TRANG_THAI')->orWhere('TRANG_THAI', 'CHUA_SU_DUNG');
        })->whereNull('MAHD');
    }

    public function scopeDaSuDung($query)
    {
        return $query->where('TRANG_THAI', 'DA_SU_DUNG')->orWhereNotNull('MAHD');
    }
}
