<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuocTroChuyen extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'cuocTroChuyen';
    protected $primaryKey = 'id';

    public function getTable()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('cuocTroChuyen')) {
                return 'cuocTroChuyen';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('cuoctrochuyen')) {
                return 'cuoctrochuyen';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('cuoc_tro_chuyen')) {
                return 'cuoc_tro_chuyen';
            }
        } catch (\Throwable $e) {}
        return 'cuocTroChuyen';
    }

    protected $fillable = [
        'khachHangId',
        'cskhNhanVienId',
        'trangThaiChat',
    ];

    protected $appends = [
        'MACUOCTROCHUYEN',
        'MAKH',
        'TRANGTHAI',
        'USERNAME_NV',
    ];

    public function setTrangThaiChatAttribute($val)
    {
        if ($val === 'DA_DONG' || $val === 1 || $val === '1') {
            $this->attributes['trangThaiChat'] = 1;
        } else {
            $this->attributes['trangThaiChat'] = 0;
        }
    }

    public function getMACUOCTROCHUYENAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getMAKHAttribute()
    {
        return $this->khachhang?->hoTen ?? ('KH-' . ($this->attributes['khachHangId'] ?? $this->id));
    }

    public function getTRANGTHAIAttribute()
    {
        return ($this->attributes['trangThaiChat'] ?? 0) == 1 ? 'DA_DONG' : 'DANG_HOAT_DONG';
    }

    public function getUsernameNvAttribute()
    {
        return $this->nhanvien?->hoTen ?? $this->cskhNhanVienId ?? 'CSKH';
    }

    public function khachhang()
    {
        return $this->belongsTo(NguoiDung::class, 'khachHangId');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, 'cskhNhanVienId');
    }

    public function tinnhans()
    {
        return $this->hasMany(TinNhan::class, 'cuocTroChuyenId', 'id');
    }
}
