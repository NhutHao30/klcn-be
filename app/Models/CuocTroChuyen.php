<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CuocTroChuyen extends Model
{
    protected $table = 'cuocTroChuyen';
    protected $primaryKey = 'id';

    public function getTable()
    {
        try {
            if (Schema::hasTable('cuocTroChuyen')) {
                return 'cuocTroChuyen';
            }
            if (Schema::hasTable('cuoc_tro_chuyen')) {
                return 'cuoc_tro_chuyen';
            }
            if (Schema::hasTable('cuoctrochuyen')) {
                return 'cuoctrochuyen';
            }
        } catch (\Throwable $e) {}
        return 'cuocTroChuyen';
    }

    public function getCreatedAtColumn()
    {
        try {
            if (Schema::hasColumn($this->getTable(), 'createdAt')) {
                return 'createdAt';
            }
        } catch (\Throwable $e) {}
        return 'created_at';
    }

    public function getUpdatedAtColumn()
    {
        try {
            if (Schema::hasColumn($this->getTable(), 'updatedAt')) {
                return 'updatedAt';
            }
        } catch (\Throwable $e) {}
        return 'updated_at';
    }

    public static function getKhachHangCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'khachHangId')) {
                return 'khachHangId';
            }
            if (Schema::hasColumn($tbl, 'khachhang_id')) {
                return 'khachhang_id';
            }
        } catch (\Throwable $e) {}
        return 'khachHangId';
    }

    public static function getTrangThaiCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'trangThaiChat')) {
                return 'trangThaiChat';
            }
            if (Schema::hasColumn($tbl, 'trang_thai_chat')) {
                return 'trang_thai_chat';
            }
        } catch (\Throwable $e) {}
        return 'trangThaiChat';
    }

    public static function getNhanVienCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'cskhNhanVienId')) {
                return 'cskhNhanVienId';
            }
            if (Schema::hasColumn($tbl, 'cskh_nhanvien_id')) {
                return 'cskh_nhanvien_id';
            }
        } catch (\Throwable $e) {}
        return 'cskhNhanVienId';
    }

    protected $fillable = [
        'khachHangId',
        'khachhang_id',
        'cskhNhanVienId',
        'cskh_nhanvien_id',
        'trangThaiChat',
        'trang_thai_chat',
    ];

    protected $appends = [
        'MACUOCTROCHUYEN',
        'MAKH',
        'TRANGTHAI',
        'USERNAME_NV',
    ];

    public function setTrangThaiChatAttribute($val)
    {
        $parsed = ($val === 'DA_DONG' || $val === 1 || $val === '1') ? 1 : 0;
        $col = static::getTrangThaiCol();
        $this->attributes[$col] = $parsed;
    }

    public function getMACUOCTROCHUYENAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getMAKHAttribute()
    {
        $khId = $this->attributes['khachHangId'] ?? $this->attributes['khachhang_id'] ?? $this->id;
        return $this->khachhang?->hoTen ?? ('KH-' . $khId);
    }

    public function getTRANGTHAIAttribute()
    {
        $st = $this->attributes['trangThaiChat'] ?? $this->attributes['trang_thai_chat'] ?? 0;
        return $st == 1 ? 'DA_DONG' : 'DANG_HOAT_DONG';
    }

    public function getUsernameNvAttribute()
    {
        $nvId = $this->attributes['cskhNhanVienId'] ?? $this->attributes['cskh_nhanvien_id'] ?? null;
        return $this->nhanvien?->hoTen ?? $nvId ?? 'CSKH';
    }

    public function khachhang()
    {
        return $this->belongsTo(NguoiDung::class, static::getKhachHangCol());
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, static::getNhanVienCol());
    }

    public function tinnhans()
    {
        $fk = TinNhan::getCuocTroChuyenCol();
        return $this->hasMany(TinNhan::class, $fk, 'id');
    }
}
