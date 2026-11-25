<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietDonHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'chiTietDonHang';

    protected $fillable = [
        'donHangId',
        'sanPhamId',
        'soLuong',
        'donGia',
        'thanhTien',
    ];

    protected $casts = [
        'soLuong' => 'integer',
        'donGia' => 'double',
        'thanhTien' => 'double',
    ];

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'donHangId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
