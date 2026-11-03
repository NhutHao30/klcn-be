<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoaiSanPham extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'loaiSanPham';

    protected $fillable = [
        'tenLoai',
        'slug',
        'moTa',
        'hinhAnh',
        'trangThai',
    ];

    protected $appends = ['MALOAI', 'TENLOAI', 'MO_TA', 'soLuongSanPham', 'SO_LUONG_SP'];

    public function getMALOAIAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENLOAIAttribute()
    {
        return $this->attributes['tenLoai'] ?? null;
    }

    public function getMOTAAttribute()
    {
        return $this->attributes['moTa'] ?? null;
    }

    public function getSoLuongSanPhamAttribute()
    {
        if (array_key_exists('san_phams_count', $this->attributes)) {
            return (int) $this->attributes['san_phams_count'];
        }
        if ($this->relationLoaded('sanPhams')) {
            return $this->sanPhams->count();
        }
        return 0;
    }

    public function getSanPhamsCountAttribute()
    {
        return $this->getSoLuongSanPhamAttribute();
    }

    public function getSOLUONGSPAttribute()
    {
        return $this->getSoLuongSanPhamAttribute();
    }

    public function sanPhams()
    {
        return $this->hasMany(SanPham::class, 'loaiId');
    }
}
