<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoaiSanPham extends Model
{
    use HasFactory;

    protected $table = 'loai_san_pham';

    protected $fillable = [
        'ten_loai',
        'slug',
        'hinh_anh',
        'trang_thai',
    ];

    protected $appends = ['MALOAI', 'TENLOAI'];

    public function getMALOAIAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENLOAIAttribute()
    {
        return $this->attributes['ten_loai'] ?? null;
    }

    public function sanPhams()
    {
        return $this->hasMany(SanPham::class, 'loai_id');
    }
}
