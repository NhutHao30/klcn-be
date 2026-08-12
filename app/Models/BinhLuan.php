<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BinhLuan extends Model
{
    protected $table = 'binhluan';
    protected $primaryKey = 'MABL';

    protected $fillable = [
        'MAKH',
        'MATINTUC',
        'PARENT_ID',
        'NGAYDANG',
        'NOIDUNG',
    ];

    public function khachhang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }

    public function tintuc()
    {
        return $this->belongsTo(TinTuc::class, 'MATINTUC', 'MATINTUC');
    }

    public function parent()
    {
        return $this->belongsTo(BinhLuan::class, 'PARENT_ID', 'MABL');
    }

    public function replies()
    {
        return $this->hasMany(BinhLuan::class, 'PARENT_ID', 'MABL')->with([
            'khachhang' => function ($q) {
                $q->select('MAKH', 'HOTEN', 'USERNAME');
            },
            'khachhang.taikhoan' => function ($q) {
                $q->select('USERNAME', 'AVATAR', 'MAROLE');
            },
            'replies'
        ])->orderBy('NGAYDANG', 'asc');
    }
}
