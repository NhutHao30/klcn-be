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

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId', 'id');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, 'nhanVienId', 'id');
    }
}
