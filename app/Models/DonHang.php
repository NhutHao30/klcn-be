<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'donHang';

    protected $fillable = [
        'maDonHang',
        'nguoidungId',
        'cuaHangId',
        'voucherId',
        'loaiDonHang', // 0: Online, 1: POS quầy
        'tongTienHang',
        'soTienGiam',
        'phiVanChuyen',
        'tongThanhToan',
        'phuongThucThanhToan', // 0: CASH/COD, 1: VNPAY, 2: MOMO
        'trangThaiThanhToan', // 0: CHUA_THANH_TOAN, 1: DA_THANH_TOAN, 2: HOAN_TIEN
        'trangThaiDonHang', // 0: CHO_XAC_NHAN, 1: CHUAN_BI_HANG, 2: DA_GIAO_GHN, 3: DANG_VAN_CHUYEN, 4: DANG_GIAO, 5: DA_GIAO_THANH_CONG, 6: DA_HUY
        'maVanDonGhn',
        'ghnStatusCode',
        'diaChiGiaoHang',
        'toWardCode',
        'toDistrictId',
        'ghiChu',
        'minhChungThanhToan',
    ];

    protected $casts = [
        'loaiDonHang' => 'integer',
        'phuongThucThanhToan' => 'integer',
        'trangThaiThanhToan' => 'integer',
        'trangThaiDonHang' => 'integer',
        'tongTienHang' => 'double',
        'soTienGiam' => 'double',
        'phiVanChuyen' => 'double',
        'tongThanhToan' => 'double',
    ];

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidungId');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function voucher()
    {
        return $this->belongsTo(KhuyenMai::class, 'voucherId');
    }

    public function chitiet()
    {
        return $this->hasMany(ChiTietDonHang::class, 'donHangId');
    }

    public function timelineGHN()
    {
        return $this->hasMany(LichSuTrangThaiDonHang::class, 'donHangId');
    }

    public function danhgia()
    {
        return $this->hasOne(DanhGiaSanPham::class, 'donHangId');
    }
}
