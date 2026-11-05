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
        'isNew',
    ];

    protected $casts = [
        'giaBan' => 'double',
        'isNew' => 'boolean',
    ];

    protected $appends = ['MASP', 'maSanPham', 'TENSP', 'GIABAN', 'HINHANH', 'MOTA', 'SOLUONG_TON', 'soLuongTon', 'TONKHO_THUCTE', 'isNew', 'IS_NEW', 'phanTramGiam', 'PHAN_TRAM_GIAM'];

    public function getMASPAttribute()
    {
        $id = $this->attributes['id'] ?? null;
        if ($id !== null) {
            return 'SP' . str_pad($id, 3, '0', STR_PAD_LEFT);
        }
        return null;
    }

    public function getMaSanPhamAttribute()
    {
        return $this->getMASPAttribute();
    }

    public function getTENSPAttribute()
    {
        return $this->attributes['tenSanPham'] ?? $this->attributes['ten_san_pham'] ?? $this->attributes['TENSP'] ?? $this->attributes['name'] ?? null;
    }

    public function getGIABANAttribute()
    {
        return $this->attributes['giaBan'] ?? $this->attributes['gia_ban'] ?? $this->attributes['GIABAN'] ?? 0;
    }

    public function getHINHANHAttribute()
    {
        return $this->attributes['hinhAnh'] ?? $this->attributes['hinh_anh'] ?? $this->attributes['HINHANH'] ?? null;
    }

    public function getMOTAAttribute()
    {
        return $this->attributes['moTa'] ?? $this->attributes['mo_ta'] ?? $this->attributes['MOTA'] ?? '';
    }

    public function getSoLuongTonAttribute()
    {
        if (array_key_exists('soLuongTon', $this->attributes) && $this->attributes['soLuongTon'] !== null) {
            return (int) $this->attributes['soLuongTon'];
        }
        if (array_key_exists('SOLUONG_TON', $this->attributes) && $this->attributes['SOLUONG_TON'] !== null) {
            return (int) $this->attributes['SOLUONG_TON'];
        }
        if (array_key_exists('TONKHO_THUCTE', $this->attributes) && $this->attributes['TONKHO_THUCTE'] !== null) {
            return (int) $this->attributes['TONKHO_THUCTE'];
        }
        if ($this->relationLoaded('tonKhoChiNhanhs')) {
            return (int) $this->tonKhoChiNhanhs->sum('soLuongTon');
        }
        return (int) $this->tonKhoChiNhanhs()->sum('soLuongTon');
    }

    public function getTonkhoThucteAttribute()
    {
        return $this->getSoLuongTonAttribute();
    }

    public function getIsNewAttribute()
    {
        if (array_key_exists('isNew', $this->attributes)) {
            return (bool) $this->attributes['isNew'];
        }
        if (!isset($this->attributes['createdAt'])) return false;
        return \Carbon\Carbon::parse($this->attributes['createdAt'])->gte(\Carbon\Carbon::now()->subDays(14));
    }

    public function getPhanTramGiamAttribute()
    {
        if ($this->relationLoaded('lichSuGias')) {
            return (float) ($this->lichSuGias->first()->phanTramGiam ?? 0);
        }
        return (float) ($this->lichSuGias()->orderBy('createdAt', 'desc')->value('phanTramGiam') ?? 0);
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
