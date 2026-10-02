<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class TaiKhoan extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'taikhoan';

    protected $fillable = [
        'username',
        'email',
        'password',
        'vaiTroId',
        'googleId',
        'trangThai',
    ];

    protected $hidden = [
        'password',
        'rememberToken',
    ];

    protected $appends = [
        'MAROLE',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // --- JWT ---
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getJWTCustomClaims()
    {
        return [
            'role_id' => $this->vaiTroId,
            'MAROLE' => $this->MAROLE,
            'email' => $this->email,
            'username' => $this->username,
        ];
    }

    /**
     * Map vaitro_id sang cờ MAROLE legacy cho Frontend Router:
     * 0: ADMIN, 1: MANAGER, 2: WAREHOUSE/CASHIER, 4: CSKH, 3: CUSTOMER
     */
    public function getMAROLEAttribute()
    {
        return match ((int)$this->attributes['vaiTroId'] ?? 1) {
            1 => 0, // ADMIN (Giao diện Quản trị)
            2 => 1, // MANAGER (Quản lý chi nhánh)
            3, 4 => 2, // WAREHOUSE / CASHIER (Nhân viên)
            5 => 4, // CSKH
            6 => 3, // CUSTOMER (Khách hàng)
            default => 0,
        };
    }

    // --- MỐI QUAN HỆ ---
    public function vaitro()
    {
        return $this->belongsTo(VaiTro::class, 'vaiTroId');
    }

    public function nguoidung()
    {
        return $this->hasOne(NguoiDung::class, 'taiKhoanId');
    }

    public function khachhang()
    {
        return $this->hasOne(NguoiDung::class, 'taiKhoanId');
    }

    public function nhanvien()
    {
        return $this->hasOne(NguoiDung::class, 'taiKhoanId');
    }
}
