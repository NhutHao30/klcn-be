<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BienBanHuy extends Model
{
    protected $table = 'bien_ban_huy';

    protected $fillable = [
        'MALOHAN',
        'MACUAHANG',
        'USERNAME',
        'SOLUONG_HUY',
        'LY_DO',
        'GHICHU',
        'NGAY_HUY',
        'KY_THU_KHO',
        'KY_KE_TOAN',
        'KY_GIAM_DOC',
        'NGUOI_KY_KE_TOAN',
        'NGUOI_KY_GIAM_DOC',
        'TRANGTHAI_KY',
    ];

    protected $casts = [
        'NGAY_HUY'    => 'datetime',
        'SOLUONG_HUY' => 'integer',
    ];

    public function loHang()
    {
        return $this->belongsTo(LoHang::class, 'MALOHAN', 'MALOHAN');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'MACUAHANG', 'MACUAHANG');
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'USERNAME');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NhanVien::class, 'USERNAME', 'USERNAME');
    }

    /**
     * Label tiếng Việt cho lý do hủy.
     */
    public function getLyDoLabelAttribute(): string
    {
        return match ($this->LY_DO) {
            'het_han'   => 'Hết hạn sử dụng',
            'hong_hong'  => 'Hỏng hóc trong bảo quản',
            'thu_hoi'    => 'Thu hồi từ nhà cung cấp',
            'khac'       => 'Lý do khác',
            default      => $this->LY_DO,
        };
    }
}
