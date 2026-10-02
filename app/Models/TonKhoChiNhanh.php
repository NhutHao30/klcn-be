<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TonKhoChiNhanh extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'tonKhoChiNhanh';

    protected $fillable = [
        'cuaHangId',
        'sanPhamId',
        'soLuongTon',
    ];

    protected $casts = [
        'soLuongTon' => 'integer',
    ];

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
