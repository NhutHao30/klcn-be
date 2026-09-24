<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhatKyKho extends Model
{
    use HasFactory;

    protected $table = 'nhatky_kho';

    protected $fillable = [
        'cuahang_id',
        'sanpham_id',
        'loai_bien_dong', // 0: Bán POS, 1: Bán Online, 2: Chuyển kho xuất, 3: Chuyển kho nhập, 4: Nhập hàng mới
        'so_luong_thay_doi',
        'so_luong_ton_sau',
        'ma_tham_chieu',
    ];

    protected $casts = [
        'loai_bien_dong' => 'integer',
        'so_luong_thay_doi' => 'integer',
        'so_luong_ton_sau' => 'integer',
    ];

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_id');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }
}
