<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HdNhap extends Model
{
    protected $table = 'hdnhap';
    protected $primaryKey = 'MAHDNHAP';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MAHDNHAP',
        'NGAYLAP',
        'USERNAME',
        'GHICHU',
        'MANCC',
        'MACUAHANG_NHAP',
        'MA_PHIEU_NCC',
        'SO_HOA_DON',
        'NGAY_HOA_DON',
        'TONGTIEN_NHAP',
        'PHUONGTHUCTHANHTOAN',
        'CHIETKHAU',
        'THUE_VAT',
        'PHI_KHAC',
        'KY_THU_KHO',
        'KY_NCC',
        'KY_KE_TOAN',
        'KY_GIAM_DOC',
        'NGUOI_KY_KE_TOAN',
        'NGUOI_KY_GIAM_DOC',
        'TRANGTHAI_KY',
    ];

    public function nhanvien()
    {
        return $this->belongsTo(NhanVien::class, 'USERNAME', 'USERNAME');
    }

    public function nhacungcap()
    {
        return $this->belongsTo(NhaCungCap::class, 'MANCC', 'MANCC');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'MACUAHANG_NHAP', 'MACUAHANG');
    }

    public function chitiets()
    {
        return $this->hasMany(ChiTietHdNhap::class, 'MAHDNHAP', 'MAHDNHAP');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'MAHDNHAP', 'MAHDNHAP');
    }
}
