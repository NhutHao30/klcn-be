<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
    use HasFactory;

    protected $table = 'vaitro';

    protected $fillable = [
        'ten_vaitro',
        'mo_ta',
    ];

    public function taiKhoans()
    {
        return $this->hasMany(TaiKhoan::class, 'vaitro_id');
    }
}
