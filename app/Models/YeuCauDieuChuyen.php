<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YeuCauDieuChuyen extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'yeuCauDieuChuyen';

    protected $fillable = [
        'cuaHangXuatId',
        'cuaHangNhapId',
        'nguoi_tao_id',
        'nguoi_duyet_id',
        'trangThai', // 0: Chờ duyệt, 1: Đang vận chuyển, 2: Đã nhận thành công, 3: Từ chối/Hủy
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
        return $this->belongsTo(NguoiDung::class, 'nguoi_tao_id');
    }

    public function nguoiDuyet()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoi_duyet_id');
    }

    public function chitiet()
    {
        return $this->hasMany(ChiTietDieuChuyen::class, 'yeuCauId');
    }
}
