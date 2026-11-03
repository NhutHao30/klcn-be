<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietBienBanHuy extends Model
{
    use HasFactory;

    protected $table = 'chiTietBienBanHuy';
    public $timestamps = false;

    protected $fillable = [
        'bienBanHuyId',
        'bien_ban_huy_id',
        'loHangId',
        'lohang_id',
        'sanPhamId',
        'sanpham_id',
        'soLuongHuy',
        'so_luong_huy',
        'donGia',
        'don_gia',
        'thanhTien',
        'thanh_tien',
    ];

    protected $casts = [
        'donGia' => 'double',
        'thanhTien' => 'double',
    ];

    public function bienBanHuy()
    {
        return $this->belongsTo(BienBanHuy::class, 'bienBanHuyId');
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
