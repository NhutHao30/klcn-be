<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietHoaDonNhap extends Model
{
    use HasFactory;

    protected $table = 'chitiet_hoadon_nhap';
    public $timestamps = false;

    protected $fillable = [
        'hoadon_nhap_id',
        'lohang_id',
        'sanpham_id',
        'so_luong',
        'don_gia_nhap',
        'thanh_tien',
    ];

    protected $casts = [
        'don_gia_nhap' => 'double',
        'thanh_tien' => 'double',
    ];

    public function hoaDonNhap()
    {
        return $this->belongsTo(HoaDonNhap::class, 'hoadon_nhap_id');
    }

    public function loHang()
    {
        return $this->belongsTo(LoHang::class, 'lohang_id');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }
}
