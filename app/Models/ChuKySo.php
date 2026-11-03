<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChuKySo extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'chuKySo';

    protected $fillable = [
        'maChuKy',
        'loaiTaiLieu',
        'taiLieuId',
        'vaiTroKy',
        'nguoiKyId',
        'tenNguoiKy',
        'digitalSignatureHash',
        'publicKey',
        'trangThai',
        'ngayKy',
        'ghiChu',
    ];

    protected $casts = [
        'ngayKy' => 'datetime',
    ];

    public function nguoiKy()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoiKyId');
    }

    public function getTenVaiTroKyAttribute()
    {
        $role = $this->vaiTroKy ?? $this->vai_tro_ky;
        return match ((int)$role) {
            1 => 'Nhân viên kế toán',
            2 => 'Nhân viên kho',
            3 => 'Quản lý chi nhánh',
            default => 'Người ký duyệt',
        };
    }
}
