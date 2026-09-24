<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TonKhoChiNhanh extends Model
{
    use HasFactory;

    protected $table = 'tonkho_chinhanh';

    protected $fillable = [
        'cuahang_id',
        'sanpham_id',
        'so_luong_ton',
    ];

    protected $casts = [
        'so_luong_ton' => 'integer',
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
