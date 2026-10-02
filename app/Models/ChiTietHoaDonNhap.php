<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietHoaDonNhap extends Model
{
    use HasFactory;

    protected $table = 'chiTietHoaDonNhap';
    public $timestamps = false;

    protected $fillable = [
        'hoadon_nhap_id',
        'loHangId',
        'sanPhamId',
        'soLuong',
        'don_gia_nhap',
        'thanhTien',
    ];

    protected $casts = [
        'don_gia_nhap' => 'double',
        'thanhTien' => 'double',
    ];

    public function hoaDonNhap()
    {
        return $this->belongsTo(HoaDonNhap::class, 'hoadon_nhap_id');
    }

    public function loHang()
    {
        return $this->belongsTo(LoHang::class, 'loHangId');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
