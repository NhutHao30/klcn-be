<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemErrorLog extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'he_thong_loi';

    protected $fillable = [
        'maLoi',
        'loaiLoi',
        'tieuDeLoi',
        'file',
        'dong',
        'stackTrace',
        'url',
        'soLanXuatHien',
        'mucDo',
        'lanCuoiXuatHien',
        'trangThai',
    ];

    protected $casts = [
        'soLanXuatHien' => 'integer',
        'trangThai' => 'integer',
        'dong' => 'integer',
        'lanCuoiXuatHien' => 'datetime',
    ];
}

