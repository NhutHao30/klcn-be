<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'posShifts';

    protected $fillable = [
        'cuaHangId',
        'nhanVienId',
        'thoiGianMoCa',
        'thoiGianDongCa',
        'tienMatDauCa',
        'doanhThuTienMat',
        'doanhThuChuyenKhoan',
        'doanhThuMomo',
        'tongDoanhThu',
        'tienMatSach',
        'tienMatThucTe',
        'chenhLech',
        'trangThai',
    ];

    protected $casts = [
        'thoiGianMoCa' => 'datetime',
        'thoiGianDongCa' => 'datetime',
        'tienMatDauCa' => 'float',
        'doanhThuTienMat' => 'float',
        'doanhThuChuyenKhoan' => 'float',
        'doanhThuMomo' => 'float',
        'tongDoanhThu' => 'float',
        'tienMatSach' => 'float',
        'tienMatThucTe' => 'float',
        'chenhLech' => 'float',
        'trangThai' => 'integer',
    ];

    protected $appends = [
        'tong_doanh_thu',
        'chenh_lech',
        'trang_thai',
        'thoi_gian_mo_ca',
        'thoi_gian_dong_ca',
        'tien_mat_dau_ca',
        'doanh_thu_tien_mat',
        'doanh_thu_chuyen_khoan',
        'doanh_thu_momo',
        'tien_mat_sach',
        'tien_mat_thuc_te',
        'cuahang_id',
        'nhanvien_id',
    ];

    public function getTongDoanhThuAttribute() { return (float)($this->attributes['tongDoanhThu'] ?? 0); }
    public function getChenhLechAttribute() { return (float)($this->attributes['chenhLech'] ?? 0); }
    public function getTrangThaiAttribute() { return (int)($this->attributes['trangThai'] ?? 0); }
    public function getThoiGianMoCaAttribute() { return $this->attributes['thoiGianMoCa'] ?? null; }
    public function getThoiGianDongCaAttribute() { return $this->attributes['thoiGianDongCa'] ?? null; }
    public function getTienMatDauCaAttribute() { return (float)($this->attributes['tienMatDauCa'] ?? 0); }
    public function getDoanhThuTienMatAttribute() { return (float)($this->attributes['doanhThuTienMat'] ?? 0); }
    public function getDoanhThuChuyenKhoanAttribute() { return (float)($this->attributes['doanhThuChuyenKhoan'] ?? 0); }
    public function getDoanhThuMomoAttribute() { return (float)($this->attributes['doanhThuMomo'] ?? 0); }
    public function getTienMatSachAttribute() { return (float)($this->attributes['tienMatSach'] ?? 0); }
    public function getTienMatThucTeAttribute() { return isset($this->attributes['tienMatThucTe']) ? (float)$this->attributes['tienMatThucTe'] : null; }
    public function getCuahangIdAttribute() { return $this->attributes['cuaHangId'] ?? null; }
    public function getNhanvienIdAttribute() { return $this->attributes['nhanVienId'] ?? null; }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId', 'id');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, 'nhanVienId', 'id');
    }
}
