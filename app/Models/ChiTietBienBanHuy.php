<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietBienBanHuy extends Model
{
    use HasFactory;

    protected $table = 'chitiet_bien_ban_huy';
    public $timestamps = false;

    protected $fillable = [
        'bien_ban_huy_id',
        'lohang_id',
        'sanpham_id',
        'so_luong_huy',
        'don_gia',
        'thanh_tien',
    ];

    protected $casts = [
        'don_gia' => 'double',
        'thanh_tien' => 'double',
    ];

    public function bienBanHuy()
    {
        return $this->belongsTo(BienBanHuy::class, 'bien_ban_huy_id');
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
