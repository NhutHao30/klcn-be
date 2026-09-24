<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    use HasFactory;

    protected $table = 'don_hang';

    protected $fillable = [
        'ma_don_hang',
        'nguoidung_id',
        'cuahang_id',
        'voucher_id',
        'loai_don_hang', // 0: Online, 1: POS quầy
        'tong_tien_hang',
        'so_tien_giam',
        'phi_van_chuyen',
        'tong_thanhtoan',
        'phuong_thuc_thanhtoan', // 0: CASH/COD, 1: VNPAY, 2: MOMO
        'trang_thai_thanhtoan', // 0: CHUA_THANH_TOAN, 1: DA_THANH_TOAN, 2: HOAN_TIEN
        'trang_thai_don_hang', // 0: CHO_XAC_NHAN, 1: CHUAN_BI_HANG, 2: DA_GIAO_GHN, 3: DANG_VAN_CHUYEN, 4: DANG_GIAO, 5: DA_GIAO_THANH_CONG, 6: DA_HUY
        'ma_van_don_ghn',
        'ghn_status_code',
        'dia_chi_giao_hang',
        'ghi_chu',
    ];

    protected $casts = [
        'loai_don_hang' => 'integer',
        'phuong_thuc_thanhtoan' => 'integer',
        'trang_thai_thanhtoan' => 'integer',
        'trang_thai_don_hang' => 'integer',
        'tong_tien_hang' => 'double',
        'so_tien_giam' => 'double',
        'phi_van_chuyen' => 'double',
        'tong_thanhtoan' => 'double',
    ];

    public function nguoidung()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoidung_id');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuahang_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function chitiet()
    {
        return $this->hasMany(ChiTietDonHang::class, 'donhang_id');
    }

    public function timelineGHN()
    {
        return $this->hasMany(LichSuTrangThaiDonHang::class, 'donhang_id');
    }

    public function danhgia()
    {
        return $this->hasOne(DanhGiaSanPham::class, 'donhang_id');
    }
}
