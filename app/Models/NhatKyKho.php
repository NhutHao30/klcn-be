<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhatKyKho extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'nhatKyKho';

    protected $fillable = [
        'cuaHangId',
        'sanPhamId',
        'loaiBienDong',
        'soLuongThayDoi',
        'soLuongTonSau',
        'maThamChieu',
    ];

    protected $casts = [
        'loaiBienDong' => 'integer',
        'soLuongThayDoi' => 'integer',
        'soLuongTonSau' => 'integer',
    ];

    protected $appends = [
        'so_luong_thay_doi',
        'so_luong_ton_sau',
        'loai_bien_dong',
        'ma_tham_chieu',
        'quantity',
        'after_quantity',
        'type',
        'reference_id',
    ];

    public function getQuantityAttribute()
    {
        return $this->attributes['soLuongThayDoi'] ?? 0;
    }

    public function getAfterQuantityAttribute()
    {
        return $this->attributes['soLuongTonSau'] ?? 0;
    }

    public function getTypeAttribute()
    {
        return $this->attributes['loaiBienDong'] ?? 0;
    }

    public function getReferenceIdAttribute()
    {
        return $this->attributes['maThamChieu'] ?? null;
    }

    // Accessors & Mutators for snake_case backward compatibility
    public function setLoaiBienDongAttribute($value)
    {
        $this->attributes['loaiBienDong'] = $value;
    }

    public function getLoaiBienDongAttribute()
    {
        return $this->attributes['loaiBienDong'] ?? null;
    }

    public function setSoLuongThayDoiAttribute($value)
    {
        $this->attributes['soLuongThayDoi'] = $value;
    }

    public function getSoLuongThayDoiAttribute()
    {
        return $this->attributes['soLuongThayDoi'] ?? null;
    }

    public function setSoLuongTonSauAttribute($value)
    {
        $this->attributes['soLuongTonSau'] = $value;
    }

    public function getSoLuongTonSauAttribute()
    {
        return $this->attributes['soLuongTonSau'] ?? null;
    }

    public function setMaThamChieuAttribute($value)
    {
        $this->attributes['maThamChieu'] = $value;
    }

    public function getMaThamChieuAttribute()
    {
        return $this->attributes['maThamChieu'] ?? null;
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
