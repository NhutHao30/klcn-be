<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChuKySo extends Model
{
    use HasFactory;

    protected $table = 'chu_ky_so';

    protected $fillable = [
        'ma_chu_ky',
        'loai_tai_lieu',
        'tai_lieu_id',
        'vai_tro_ky',
        'nguoi_ky_id',
        'ten_nguoi_ky',
        'digital_signature_hash',
        'public_key',
        'trang_thai',
        'ngay_ky',
        'ghi_chu',
    ];

    protected $casts = [
        'ngay_ky' => 'datetime',
    ];

    public function nguoiKy()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoi_ky_id');
    }

    public function getTenVaiTroKyAttribute()
    {
        return match ((int)$this->vai_tro_ky) {
            1 => 'Kế toán',
            2 => 'Quản lý kho',
            3 => 'Chủ cửa hàng / Quản lý chi nhánh',
            4 => 'Đại diện Nhà cung cấp',
            default => 'Không xác định',
        };
    }
}
