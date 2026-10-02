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
        'hinhAnh',
        'trangThai',
    ];

    protected $appends = ['MALOAI', 'TENLOAI'];

    public function getMALOAIAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENLOAIAttribute()
    {
        return $this->attributes['tenLoai'] ?? null;
    }

    public function sanPhams()
    {
        return $this->hasMany(SanPham::class, 'loaiId');
    }
}
