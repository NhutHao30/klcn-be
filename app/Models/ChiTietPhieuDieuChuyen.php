<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietPhieuDieuChuyen extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'chitiet_phieu_dieuchuyen';

    protected $fillable = [
        'phieuId',
        'sanPhamId',
        'soLuongXuat',
        'soLuongNhan',
        'lyDoHaoHut',
    ];

    protected $casts = [
        'soLuongXuat' => 'integer',
        'soLuongNhan' => 'integer',
    ];

    public function phieu()
    {
        return $this->belongsTo(PhieuDieuChuyen::class, 'phieuId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
