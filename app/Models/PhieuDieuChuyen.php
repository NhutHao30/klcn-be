<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhieuDieuChuyen extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'phieudieuchuyen';

    protected $fillable = [
        'yeuCauId',
        'cuaHangXuatId',
        'cuaHangNhapId',
        'nguoiTaoId',
        'trangThai',
        'hinhThucVanChuyen',
        'maVanDon',
        'nguoiVanChuyen',
        'phiVanChuyen',
        'ghiChu',
    ];

    protected $casts = [
        'phiVanChuyen' => 'double',
    ];

    public function yeucau()
    {
        return $this->belongsTo(YeuCauDieuChuyen::class, 'yeuCauId');
    }

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

    public function chitiet()
    {
        return $this->hasMany(ChiTietPhieuDieuChuyen::class, 'phieuId');
    }
}
