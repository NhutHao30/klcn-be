<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'vaitro';

    protected $fillable = [
        'ten_vaitro',
        'moTa',
    ];

    public function taiKhoans()
    {
        return $this->hasMany(TaiKhoan::class, 'vaiTroId');
    }
}
