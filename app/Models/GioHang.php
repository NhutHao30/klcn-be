<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GioHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'gioHang';

    protected $fillable = [
        'nguoidungId',
    ];

    public function nguoiDung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietGioHang::class, 'gioHangId');
    }
}
