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
        'maHoaDonNhap',
        'nhaCungCapId',
        'cuaHangId',
        'nguoiNhapId',
        'ngayNhap',
        'tongTien',
        'ghiChu',
        'trangThai',
    ];

    protected $casts = [
        'ngayNhap' => 'datetime',
        'tongTien' => 'double',
    ];

    protected $appends = [
        'MAHDNHAP', 'MANCC', 'MACUAHANG', 'NGAYLAP', 'TONGTIEN_NHAP', 'TONGTIEN', 'TRANGTHAI'
    ];

    public function getMAHDNHAPAttribute()
    {
        return $this->attributes['maHoaDonNhap'] ?? $this->attributes['ma_hoadon_nhap'] ?? $this->attributes['id'] ?? null;
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
        return $this->attributes['ngayNhap'] ?? $this->attributes['ngay_nhap'] ?? null;
    }

    public function getTONGTIENNHAPAttribute()
    {
        return $this->attributes['tongTien'] ?? $this->attributes['tong_tien'] ?? 0;
    }

    public function getTONGTIENAttribute()
    {
        return $this->attributes['tongTien'] ?? $this->attributes['tong_tien'] ?? 0;
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
        return $this->belongsTo(NguoiDung::class, 'nguoiNhapId');
    }

    public function chuKySos()
    {
        return $this->hasMany(ChuKySo::class, 'taiLieuId')->where('loaiTaiLieu', 1);
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietHoaDonNhap::class, 'hoaDonNhapId');
    }

    /**
     * Hóa đơn nhập yêu cầu đủ các chữ ký liên quan
     */
    public function kiemTraDaKyDu(): bool
    {
        $signedRolesCount = $this->chuKySos()->where('trangThai', 1)->distinct('vaiTroKy')->count('vaiTroKy');
        return $signedRolesCount >= 3;
    }
}
