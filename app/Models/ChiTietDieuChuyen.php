<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietDieuChuyen extends Model
{
    use HasFactory;

    protected $table = 'chitiet_dieuchuyen';

    protected $fillable = [
        'yeucau_id',
        'sanpham_id',
        'so_luong_chuyen',
        'so_luong_thuc_nhan',
    ];

    protected $casts = [
        'so_luong_chuyen' => 'integer',
        'so_luong_thuc_nhan' => 'integer',
    ];

    public function yeucau()
    {
        return $this->belongsTo(YeuCauDieuChuyen::class, 'yeucau_id');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }
}
