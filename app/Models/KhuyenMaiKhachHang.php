<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhuyenMaiKhachHang extends Model
{
    protected $table = 'khuyenmai_khachhang';

    protected $fillable = [
        'MA_VOUCHER',
        'MAKH',
        'MAHD',
        'NGAY_SU_DUNG',
    ];

    public function voucher()
    {
        return $this->belongsTo(KhuyenMai::class, 'MA_VOUCHER', 'MA_VOUCHER');
    }

    public function khachhang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }
}
