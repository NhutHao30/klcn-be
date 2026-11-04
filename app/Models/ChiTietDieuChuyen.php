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

    public function getTable()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('chiTietDieuChuyen')) {
                return 'chiTietDieuChuyen';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('chitietdieuchuyen')) {
                return 'chitietdieuchuyen';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('chitiet_dieuchuyen')) {
                return 'chitiet_dieuchuyen';
            }
        } catch (\Throwable $e) {}
        return 'chiTietDieuChuyen';
    }

    protected $fillable = [
        'yeuCauId',
        'sanPhamId',
        'soLuongYeuCau',
        'soLuongThucNhan',
    ];

    protected $casts = [
        'soLuongYeuCau' => 'integer',
        'soLuongThucNhan' => 'integer',
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
