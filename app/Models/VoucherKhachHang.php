<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherKhachHang extends Model
{
    use HasFactory;

    protected $table = 'voucher_khachhang';

    protected $fillable = [
        'nguoidungId',
        'maVoucher',
        'maDonHang',
        'ngayThuThap',
        'ngaySuDung',
        'trangThai',
    ];

    protected $casts = [
        'ngayThuThap' => 'datetime',
        'ngaySuDung' => 'datetime',
        'trangThai' => 'integer',
    ];

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'maVoucher', 'MA_VOUCHER');
    }

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'maDonHang', 'maDonHang');
    }
}
