<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YeuCauDieuChuyen extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'yeucaudieuchuyen';

    protected $fillable = [
        'cuaHangXuatId',
        'cuaHangNhapId',
        'nguoiTaoId',
        'nguoiDuyetId',
        'trangThai', // 0: Chờ tiếp nhận, 1: Đang xử lý, 2: Hoàn thành, 3: Hủy
        'ghiChu',
    ];

    protected $casts = [
        'trangThai' => 'integer',
    ];

    public function cuahangXuat()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangXuatId');
    }

    public function cuahangNhap()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangNhapId');
    }

    public function nguoiTao()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoiTaoId');
    }

    public function nguoiDuyet()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoiDuyetId');
    }

    public function chitiet()
    {
        return $this->hasMany(ChiTietDieuChuyen::class, 'yeuCauId');
    }

    public function phieu()
    {
        return $this->hasMany(PhieuDieuChuyen::class, 'yeuCauId');
    }
}
