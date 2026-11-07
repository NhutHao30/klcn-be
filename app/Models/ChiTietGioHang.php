<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietGioHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'chiTietGioHang';

    protected $fillable = [
        'gioHangId',
        'sanPhamId',
        'soLuong',
    ];

    public function giohang()
    {
        return $this->belongsTo(GioHang::class, 'gioHangId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
