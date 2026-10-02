<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'wishlist';

    protected $fillable = [
        'nguoidungId',
        'sanPhamId',
    ];

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }
}
