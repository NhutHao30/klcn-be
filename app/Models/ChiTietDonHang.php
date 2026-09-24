<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietDonHang extends Model
{
    use HasFactory;

    protected $table = 'chitiet_don_hang';

    protected $fillable = [
        'donhang_id',
        'sanpham_id',
        'so_luong',
        'don_gia',
        'thanh_tien',
    ];

    protected $casts = [
        'so_luong' => 'integer',
        'don_gia' => 'double',
        'thanh_tien' => 'double',
    ];

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'donhang_id');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }
}
