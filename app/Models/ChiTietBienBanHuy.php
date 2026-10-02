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
        'bien_ban_huy_id',
        'loHangId',
        'sanPhamId',
        'so_luong_huy',
        'donGia',
        'thanhTien',
    ];

    protected $casts = [
        'donGia' => 'double',
        'thanhTien' => 'double',
    ];

    public function bienBanHuy()
    {
        return $this->belongsTo(BienBanHuy::class, 'bien_ban_huy_id');
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
