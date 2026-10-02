<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietDieuChuyen extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'chiTietDieuChuyen';

    protected $fillable = [
        'yeuCauId',
        'sanPhamId',
        'so_luong_chuyen',
        'so_luong_thuc_nhan',
    ];

    protected $casts = [
        'so_luong_chuyen' => 'integer',
        'so_luong_thuc_nhan' => 'integer',
    ];

    public function yeucau()
    {
        return $this->belongsTo(YeuCauDieuChuyen::class, 'yeuCauId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
