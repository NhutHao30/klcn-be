<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SanPham extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'sanPham';

    protected $fillable = [
        'loaiId',
        'tenSanPham',
        'slug',
        'giaBan',
        'hinhAnh',
        'moTa',
        'donViTinh',
        'trangThai',
    ];

    protected $casts = [
        'giaBan' => 'double',
    ];

    protected $appends = ['MASP', 'TENSP', 'GIABAN', 'HINHANH', 'SOLUONG_TON', 'soLuongTon', 'TONKHO_THUCTE'];

    public function getMASPAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENSPAttribute()
    {
        return $this->attributes['tenSanPham'] ?? null;
    }

    public function getGIABANAttribute()
    {
        return $this->attributes['giaBan'] ?? null;
    }

    public function getHINHANHAttribute()
    {
        return $this->attributes['hinhAnh'] ?? null;
    }

    public function getSoLuongTonAttribute()
    {
        if ($this->relationLoaded('tonKhoChiNhanhs')) {
            return (int) $this->tonKhoChiNhanhs->sum('soLuongTon');
        }
        return (int) $this->tonKhoChiNhanhs()->sum('soLuongTon');
    }

    public function getTonkhoThucteAttribute()
    {
        return $this->getSoLuongTonAttribute();
    }

    public function loaiSanPham()
    {
        return $this->belongsTo(LoaiSanPham::class, 'loaiId');
    }

    public function loai()
    {
        return $this->belongsTo(LoaiSanPham::class, 'loaiId');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'sanPhamId');
    }

    public function tonKhoChiNhanhs()
    {
        return $this->hasMany(TonKhoChiNhanh::class, 'sanPhamId');
    }

    public function tonkho()
    {
        return $this->hasMany(TonKhoChiNhanh::class, 'sanPhamId');
    }

    public function lichSuGias()
    {
        return $this->hasMany(LichSuGiaSanPham::class, 'sanPhamId')->orderBy('createdAt', 'desc');
    }
}
