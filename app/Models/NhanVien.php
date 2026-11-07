<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhanVien extends Model
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
        'caLamViec',
        'luong',
    ];

    protected $appends = [
        'USERNAME',
        'HOTEN',
        'SDT',
        'GioiTinh',
        'NGAYSINH',
        'DIACHI',
        'MANV',
        'MACUAHANG',
        'CHUCVU',
        'LUONG',
        'TRANGTHAI',
        'CALAMVIEC',
        'CCCD',
    ];

    // Global scope or boot to ensure loaiNguoiDung = 1 for staff
    protected static function booted()
    {
        static::addGlobalScope('staff', function ($builder) {
            $builder->whereIn('loaiNguoiDung', [1, 2]);
        });

        static::creating(function ($model) {
            $model->loaiNguoiDung = 1;
        });
    }

    public static function findByUsername($username)
    {
        if (!$username) return null;
        return static::whereHas('taikhoan', function ($q) use ($username) {
            $q->where('username', $username);
        })->orWhere('cccdNumber', $username)
          ->orWhere('id', $username)
          ->first();
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    // Accessors for legacy column compatibility
    public function getUSERNAMEAttribute()
    {
        return $this->taikhoan?->username ?? null;
    }

    public function getHOTENAttribute()
    {
        return $this->attributes['hoTen'] ?? null;
    }

    public function getSDTAttribute()
    {
        return $this->attributes['soDienThoai'] ?? null;
    }

    public function getGioiTinhAttribute()
    {
        $val = $this->attributes['gioiTinh'] ?? null;
        if ((int)$val === 1) return 'Nam';
        if ((int)$val === 0 && !is_null($val)) return 'Nữ';
        return 'Khác';
    }

    public function getNGAYSINHAttribute()
    {
        return $this->attributes['ngaySinh'] ?? null;
    }

    public function getDIACHIAttribute()
    {
        return null;
    }

    public function getMANVAttribute()
    {
        return 'NV' . sprintf('%06d', $this->attributes['id'] ?? 0);
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuaHangId'] ?? 1;
    }

    public function getCHUCVUAttribute()
    {
        return $this->taikhoan?->vaitro?->tenVaiTro ?? 'Nhân viên';
    }

    public function getLUONGAttribute()
    {
        return (float) ($this->attributes['luong'] ?? 0);
    }

    public function getTRANGTHAIAttribute()
    {
        return ($this->taikhoan?->trangThai ?? 1) === 1 ? 'Đang làm việc' : 'Nghỉ việc';
    }

    public function getCALAMVIECAttribute()
    {
        return $this->attributes['caLamViec'] ?? 'Ca Sáng';
    }

    public function getCCCDAttribute()
    {
        return $this->attributes['cccdNumber'] ?? null;
    }
}
