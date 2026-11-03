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
        'loHangId',
        'sanPhamId',
        'soLuongHuy',
        'donGia',
        'thanhTien',
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
