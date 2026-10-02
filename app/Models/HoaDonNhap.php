<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoaDonNhap extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'hoaDonNhap';

    protected $fillable = [
        'ma_hoadon_nhap',
        'nhaCungCapId',
        'cuaHangId',
        'nguoi_nhap_id',
        'ngay_nhap',
        'tong_tien',
        'ghiChu',
        'trangThai',
    ];

    protected $casts = [
        'ngay_nhap' => 'datetime',
        'tong_tien' => 'double',
    ];

    protected $appends = [
        'MAHDNHAP', 'MANCC', 'MACUAHANG', 'NGAYLAP', 'TONGTIEN_NHAP', 'TONGTIEN', 'TRANGTHAI'
    ];

    public function getMAHDNHAPAttribute()
    {
        return $this->attributes['ma_hoadon_nhap'] ?? $this->attributes['id'] ?? null;
    }

    public function getMANCCAttribute()
    {
        return $this->attributes['nhaCungCapId'] ?? null;
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuaHangId'] ?? null;
    }

    public function getNGAYLAPAttribute()
    {
        return $this->attributes['ngay_nhap'] ?? null;
    }

    public function getTONGTIENNHAPAttribute()
    {
        return $this->attributes['tong_tien'] ?? 0;
    }

    public function getTONGTIENAttribute()
    {
        return $this->attributes['tong_tien'] ?? 0;
    }

    public function getTRANGTHAIAttribute()
    {
        return $this->attributes['trangThai'] ?? 0;
    }

    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nhaCungCapId');
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function nguoiNhap()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoi_nhap_id');
    }

    public function chuKySos()
    {
        return $this->hasMany(ChuKySo::class, 'tai_lieu_id')->where('loai_tai_lieu', 1);
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietHoaDonNhap::class, 'hoadon_nhap_id');
    }

    /**
     * Hóa đơn nhập yêu cầu đủ 4 chữ ký: Kế toán (1), Thủ kho (2), Chủ cửa hàng (3), NCC (4).
     */
    public function kiemTraDaKyDu(): bool
    {
        $signedRolesCount = $this->chuKySos()->where('trangThai', 1)->distinct('vai_tro_ky')->count('vai_tro_ky');
        return $signedRolesCount >= 4;
    }
}
