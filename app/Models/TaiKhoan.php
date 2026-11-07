<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class TaiKhoan extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'taikhoan';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'username',
        'email',
        'password',
        'vaiTroId',
        'googleId',
        'trangThai',
        'avatar',
        'chuKySo',
    ];

    protected $hidden = [
        'password',
        'rememberToken',
    ];

    protected $appends = [
        'MAROLE',
        'tenVaiTro',
        'avatar',
        'chuKySo',
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

    public function getTenVaiTroAttribute()
    {
        return $this->vaitro?->tenVaiTro ?? 'ADMIN';
    }

    public function getAvatarAttribute()
    {
        if (!empty($this->attributes['avatar'])) {
            return $this->attributes['avatar'];
        }
        if ($this->relationLoaded('nguoidung') && !empty($this->nguoidung?->attributes['avatar'])) {
            return $this->nguoidung->attributes['avatar'];
        }
        return null;
    }

    public function getChuKySoAttribute()
    {
        if (!empty($this->attributes['chuKySo'])) {
            return $this->attributes['chuKySo'];
        }
        if ($this->relationLoaded('nguoidung') && !empty($this->nguoidung?->attributes['chuKySo'])) {
            return $this->nguoidung->attributes['chuKySo'];
        }
        return null;
    }

    /**
     * Map vaitro_id sang cờ MAROLE cho Frontend Router:
     * 0: ADMIN, 1: MANAGER, 2: CASHIER, 3: CUSTOMER, 4: CSKH, 5: DIRECTOR, 6: ACCOUNTANT, 7: WAREHOUSE
     */
    public function getMAROLEAttribute()
    {
        return match ((int)($this->attributes['vaiTroId'] ?? 1)) {
            1 => 0, // ADMIN
            2 => 1, // MANAGER
            3 => 2, // CASHIER
            4 => 7, // WAREHOUSE (Nhân viên kho)
            5 => 4, // CSKH
            6 => 3, // CUSTOMER
            7 => 6, // ACCOUNTANT
            8 => 5, // DIRECTOR
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
