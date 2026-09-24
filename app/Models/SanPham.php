<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SanPham extends Model
{
    use HasFactory;

    protected $table = 'san_pham';

    protected $fillable = [
        'loai_id',
        'ten_san_pham',
        'slug',
        'gia_ban',
        'hinh_anh',
        'mo_ta',
        'don_vi_tinh',
        'trang_thai',
    ];

    protected $casts = [
        'gia_ban' => 'double',
    ];

    protected $appends = ['MASP', 'TENSP', 'GIABAN', 'HINHANH', 'SOLUONG_TON', 'so_luong_ton', 'TONKHO_THUCTE'];

    public function getMASPAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENSPAttribute()
    {
        return $this->attributes['ten_san_pham'] ?? null;
    }

    public function getGIABANAttribute()
    {
        return $this->attributes['gia_ban'] ?? null;
    }

    public function getHINHANHAttribute()
    {
        return $this->attributes['hinh_anh'] ?? null;
    }

    public function getSoLuongTonAttribute()
    {
        if ($this->relationLoaded('tonKhoChiNhanhs')) {
            return (int) $this->tonKhoChiNhanhs->sum('so_luong_ton');
        }
        return (int) $this->tonKhoChiNhanhs()->sum('so_luong_ton');
    }

    public function getTonkhoThucteAttribute()
    {
        return $this->getSoLuongTonAttribute();
    }

    public function loaiSanPham()
    {
        return $this->belongsTo(LoaiSanPham::class, 'loai_id');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'sanpham_id');
    }

    public function tonKhoChiNhanhs()
    {
        return $this->hasMany(TonKhoChiNhanh::class, 'sanpham_id');
    }

    public function lichSuGias()
    {
        return $this->hasMany(LichSuGiaSanPham::class, 'sanpham_id')->orderBy('created_at', 'desc');
    }
}
