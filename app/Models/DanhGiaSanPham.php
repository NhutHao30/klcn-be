<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanhGiaSanPham extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'danhGiaSanPham';
    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'donHangId',
        'sanPhamId',
        'nguoidungId',
        'soSao',
        'noiDung',
        'hinhAnhKemTheo',
        'trangThai',
        'phanHoiCskh',
        'cskhId',
        'ngayPhanHoi',
    ];

    public $timestamps = true;

    protected $appends = [
        'MADG',
        'MASP',
        'MAHD',
        'SO_SAO',
        'NOI_DUNG',
        'HINH_ANH',
        'NGAY_DG',
        'TEN_KHACH_HANG',
        'AVATAR',
        'PHAN_HOI_CSKH',
    ];

    public function getPHANHOICSKHAttribute()
    {
        return $this->attributes['phanHoiCskh'] ?? null;
    }

    public function getMADGAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getMASPAttribute()
    {
        return $this->sanpham?->MASP ?? ($this->attributes['sanPhamId'] ? 'SP' . str_pad($this->attributes['sanPhamId'], 3, '0', STR_PAD_LEFT) : null);
    }

    public function getMAHDAttribute()
    {
        return $this->donhang?->maDonHang ?? ($this->attributes['donHangId'] ?? null);
    }

    public function getSOSAOAttribute()
    {
        return $this->attributes['soSao'] ?? 5;
    }

    public function getNOIDUNGAttribute()
    {
        return $this->attributes['noiDung'] ?? '';
    }

    public function getHINHANHAttribute()
    {
        return $this->attributes['hinhAnhKemTheo'] ?? null;
    }

    public function getNGAYDGAttribute()
    {
        return isset($this->attributes['createdAt']) 
            ? \Carbon\Carbon::parse($this->attributes['createdAt'])->format('d/m/Y H:i') 
            : '';
    }

    public function getTENKHACHHANGAttribute()
    {
        return $this->nguoidung?->hoTen ?? 'Khách hàng';
    }

    public function getAVATARAttribute()
    {
        return $this->nguoidung?->avatar ?? null;
    }

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'sanPhamId');
    }

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'donHangId');
    }

    public function binhLuans()
    {
        return $this->hasMany(BinhLuanDanhGia::class, 'danhGiaId')
            ->where('trangThai', 1)
            ->orderBy('id', 'asc');
    }
}
