<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoHang extends Model
{
    use HasFactory;

    protected $table = 'lo_hang';

    protected $fillable = [
        'ma_lo_hang',
        'sanpham_id',
        'cuahang_id',
        'nhacungcap_id',
        'so_luong_nhap',
        'so_luong_con',
        'ngay_san_xuat',
        'hsd',
        'gia_nhap',
        'trang_thai',
    ];

    protected $casts = [
        'ngay_san_xuat' => 'date',
        'hsd' => 'date',
        'gia_nhap' => 'double',
    ];

    protected $appends = [
        'MALOHAN', 'MAHDNHAP', 'MASP', 'MACUAHANG', 'MANCC',
        'SOLUONG_CON', 'SOLUONG_NHAP', 'DONGIA_NHAP', 'NGAYSX', 'HSD', 'HSD_LO'
    ];

    public function getMALOHANAttribute()
    {
        return $this->attributes['ma_lo_hang'] ?? $this->attributes['id'] ?? null;
    }

    public function getMAHDNHAPAttribute()
    {
        return $this->attributes['hoadon_nhap_id'] ?? null;
    }

    public function getMASPAttribute()
    {
        return $this->attributes['sanpham_id'] ?? null;
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuahang_id'] ?? null;
    }

    public function getMANCCAttribute()
    {
        return $this->attributes['nhacungcap_id'] ?? null;
    }

    public function getSOLUONGCONAttribute()
    {
        return $this->attributes['so_luong_con'] ?? 0;
    }

    public function getSOLUONGNHAPAttribute()
    {
        return $this->attributes['so_luong_nhap'] ?? 0;
    }

    public function getDONGIANHAPAttribute()
    {
        return $this->attributes['gia_nhap'] ?? 0;
    }

    public function getNGAYSXAttribute()
    {
        return $this->attributes['ngay_san_xuat'] ?? null;
    }

    public function getHSDAttribute()
    {
        return $this->attributes['hsd'] ?? null;
    }

    public function getHSDLOAttribute()
    {
        return $this->attributes['hsd'] ?? null;
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'sanpham_id');
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_id');
    }

    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nhacungcap_id');
    }

    public function kiemTraHetHan(): bool
    {
        return $this->hsd && $this->hsd->isPast();
    }
}
