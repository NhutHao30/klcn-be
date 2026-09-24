<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YeuCauDieuChuyen extends Model
{
    use HasFactory;

    protected $table = 'yeucau_dieuchuyen';

    protected $fillable = [
        'cuahang_xuat_id',
        'cuahang_nhap_id',
        'nguoi_tao_id',
        'nguoi_duyet_id',
        'trang_thai', // 0: Chờ duyệt, 1: Đang vận chuyển, 2: Đã nhận thành công, 3: Từ chối/Hủy
        'ghi_chu',
    ];

    protected $casts = [
        'trang_thai' => 'integer',
    ];

    public function cuahangXuat()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_xuat_id');
    }

    public function cuahangNhap()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_nhap_id');
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
        return $this->hasMany(ChiTietDieuChuyen::class, 'yeucau_id');
    }
}
