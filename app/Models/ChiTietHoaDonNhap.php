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
        'hoaDonNhapId',
        'loHangId',
        'sanPhamId',
        'soLuong',
        'donGiaNhap',
        'thanhTien',
    ];

    protected $casts = [
        'donGiaNhap' => 'double',
        'thanhTien' => 'double',
    ];

    public function hoaDonNhap()
    {
        return $this->belongsTo(HoaDonNhap::class, 'hoaDonNhapId');
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
