<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'loHang';

    protected $fillable = [
        'maLoHang',
        'sanPhamId',
        'cuaHangId',
        'nhaCungCapId',
        'soLuongNhap',
        'soLuongCon',
        'ngaySanXuat',
        'hsd',
        'giaNhap',
        'trangThai',
    ];

    protected $casts = [
        'ngaySanXuat' => 'date',
        'hsd' => 'date',
        'giaNhap' => 'double',
    ];

    protected $appends = [
        'MALOHAN', 'MALO', 'MAHDNHAP', 'MASP', 'MACUAHANG', 'MANCC',
        'SOLUONG_CON', 'SOLUONG_NHAP', 'SOLUONGTCT', 'SOLUONGTN', 'SOLUONG',
        'DONGIA_NHAP', 'NGAYSX', 'HSD', 'HSD_LO', 'HANSUDUNG', 'so_ngay_con_lai'
    ];

    public function getMALOAttribute()
    {
        return $this->attributes['maLoHang'] ?? (string)$this->attributes['id'] ?? null;
    }

    public function getMALOHANAttribute()
    {
        return $this->attributes['maLoHang'] ?? (string)$this->attributes['id'] ?? null;
    }

    public function getSOLUONGTCTAttribute()
    {
        return $this->attributes['soLuongNhap'] ?? 0;
    }

    public function getSOLUONGTNAttribute()
    {
        return $this->attributes['soLuongCon'] ?? $this->attributes['soLuongNhap'] ?? 0;
    }

    public function getSOLUONGAttribute()
    {
        return $this->attributes['soLuongNhap'] ?? 0;
    }

    public function getMAHDNHAPAttribute()
    {
        return $this->attributes['hoadon_nhap_id'] ?? $this->hoaDonNhap?->maHoaDonNhap ?? null;
    }

    public function getMASPAttribute()
    {
        return $this->attributes['sanPhamId'] ?? null;
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuaHangId'] ?? null;
    }

    public function getMANCCAttribute()
    {
        return $this->attributes['nhaCungCapId'] ?? null;
    }

    public function getSOLUONGCONAttribute()
    {
        return $this->attributes['soLuongCon'] ?? 0;
    }

    public function getSOLUONGNHAPAttribute()
    {
        return $this->attributes['soLuongNhap'] ?? 0;
    }

    public function getDONGIANHAPAttribute()
    {
        return $this->attributes['giaNhap'] ?? 0;
    }

    public function getNGAYSXAttribute()
    {
        return $this->attributes['ngaySanXuat'] ?? null;
    }

    public function getHSDAttribute()
    {
        return $this->attributes['hsd'] ?? null;
    }

    public function getHSDLOAttribute()
    {
        return $this->attributes['hsd'] ?? null;
    }

    public function getHANSUDUNGAttribute()
    {
        return $this->attributes['hsd'] ?? null;
    }

    public function getSoNgayConLaiAttribute()
    {
        if (empty($this->attributes['hsd'])) {
            return null;
        }
        $today = \Carbon\Carbon::today();
        $exp = \Carbon\Carbon::parse($this->attributes['hsd'])->startOfDay();
        return (int) $today->diffInDays($exp, false);
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nhaCungCapId');
    }

    public function chiTietHoaDonNhap()
    {
        return $this->hasOne(ChiTietHoaDonNhap::class, 'loHangId');
    }

    public function hoaDonNhap()
    {
        return $this->hasOneThrough(HoaDonNhap::class, ChiTietHoaDonNhap::class, 'loHangId', 'id', 'id', 'hoaDonNhapId');
    }

    public function kiemTraHetHan(): bool
    {
        return $this->hsd && $this->hsd->isPast();
    }
}
