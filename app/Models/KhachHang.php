<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KhachHang extends Model
{
    use HasFactory;

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'nguoidung';

    protected $fillable = [
        'taiKhoanId',
        'cuaHangId',
        'hoTen',
        'soDienThoai',
        'gioiTinh',
        'ngaySinh',
        'cccdNumber',
        'cccdImageUrl',
        'loaiNguoiDung',
    ];

    protected $appends = [
        'MAKH',
        'USERNAME',
        'HOTEN',
        'SDT',
    ];

    protected static function booted()
    {
        static::addGlobalScope('customer', function ($builder) {
            $builder->where('loaiNguoiDung', 0);
        });

        static::creating(function ($model) {
            $model->loaiNguoiDung = 0;
        });
    }

    public static function findByUsername($username)
    {
        if (!$username) return null;
        return static::whereHas('taikhoan', function ($q) use ($username) {
            $q->where('username', $username);
        })->first();
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }

    public function getMAKHAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getHOTENAttribute()
    {
        return $this->attributes['hoTen'] ?? 'Khách Hàng';
    }

    public function getSDTAttribute()
    {
        return $this->attributes['soDienThoai'] ?? '';
    }

    public function getUSERNAMEAttribute()
    {
        return $this->taikhoan?->username ?? '';
    }
}
