<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'voucher';
    protected $primaryKey = 'MA_VOUCHER';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'MA_VOUCHER',
        'TEN_VOUCHER',
        'LOAI_GIAM',
        'GIA_TRI',
        'DON_TOI_THIEU',
        'GIAM_TOI_DA',
        'SO_LUONG_GIOI_HAN',
        'SO_LUONG_DA_DUNG',
        'NGAY_BAT_DAU',
        'NGAY_KET_THUC',
        'TRANG_THAI',
    ];

    protected $appends = [
        'MA_VOUCHER',
        'TEN_VOUCHER',
        'LOAI_GIAM',
        'GIA_TRI',
        'DON_TOI_THIEU',
        'GIAM_TOI_DA',
        'SO_LUONG_GIOI_HAN',
        'SO_LUONG_DA_DUNG',
        'NGAY_BAT_DAU',
        'NGAY_KET_THUC',
        'TRANG_THAI',
        'isValid',
    ];

    public function getMAVOUCHERAttribute()
    {
        return $this->attributes['MA_VOUCHER'] ?? $this->attributes['ma_voucher'] ?? $this->attributes['maVoucher'] ?? null;
    }

    public function getTENVOUCHERAttribute()
    {
        $name = $this->attributes['TEN_VOUCHER'] ?? $this->attributes['ten_voucher'] ?? $this->attributes['tenVoucher'] ?? null;
        if ($name) return $name;
        $code = $this->getMAVOUCHERAttribute();
        return $code ? "Ưu đãi mã {$code}" : 'Chương trình khuyến mãi';
    }

    public function getLOAIGIAMAttribute()
    {
        $val = $this->attributes['LOAI_GIAM'] ?? $this->attributes['loai_giam'] ?? $this->attributes['loaiGiam'] ?? null;
        if ($val === 'PHAN_TRAM' || $val === 'TIEN_MAT') {
            return $val;
        }
        $legacy = $this->attributes['loai_giam_gia'] ?? $this->attributes['loaiGiamGia'] ?? null;
        if ($legacy !== null) {
            return ((int)$legacy === 0) ? 'PHAN_TRAM' : 'TIEN_MAT';
        }
        return 'TIEN_MAT';
    }

    public function getGIATRIAttribute()
    {
        return (float)($this->attributes['GIA_TRI'] ?? $this->attributes['gia_tri'] ?? $this->attributes['gia_tri_giam'] ?? $this->attributes['giaTriGiam'] ?? 0);
    }

    public function getDONTOITHIEUAttribute()
    {
        return (float)($this->attributes['DON_TOI_THIEU'] ?? $this->attributes['don_toi_thieu'] ?? $this->attributes['don_hang_toi_thieu'] ?? $this->attributes['donHangToiThieu'] ?? 0);
    }

    public function getGIAMTOIDAAttribute()
    {
        $val = $this->attributes['GIAM_TOI_DA'] ?? $this->attributes['giam_toi_da'] ?? $this->attributes['giamToiDa'] ?? null;
        return $val !== null ? (float)$val : null;
    }

    public function getSOLUONGGIOIHANAttribute()
    {
        return (int)($this->attributes['SO_LUONG_GIOI_HAN'] ?? $this->attributes['so_luong_gioi_han'] ?? $this->attributes['so_luong'] ?? $this->attributes['soLuong'] ?? 100);
    }

    public function getSOLUONGDADUNGAttribute()
    {
        return (int)($this->attributes['SO_LUONG_DA_DUNG'] ?? $this->attributes['so_luong_da_dung'] ?? $this->attributes['da_su_dung'] ?? $this->attributes['daSuDung'] ?? 0);
    }

    public function getNGAYBATDAUAttribute()
    {
        return $this->attributes['NGAY_BAT_DAU'] ?? $this->attributes['ngay_bat_dau'] ?? $this->attributes['ngayBatDau'] ?? null;
    }

    public function getNGAYKETTHUCAttribute()
    {
        return $this->attributes['NGAY_KET_THUC'] ?? $this->attributes['ngay_ket_thuc'] ?? $this->attributes['ngayKetThuc'] ?? null;
    }

    public function getTRANGTHAIAttribute()
    {
        $val = $this->attributes['TRANG_THAI'] ?? $this->attributes['trang_thai'] ?? $this->attributes['trangThai'] ?? 1;
        return (int)$val;
    }

    public function getIsValidAttribute()
    {
        $now = now();
        $start = $this->NGAY_BAT_DAU ? \Carbon\Carbon::parse($this->NGAY_BAT_DAU) : null;
        $end = $this->NGAY_KET_THUC ? \Carbon\Carbon::parse($this->NGAY_KET_THUC) : null;

        return (int)$this->TRANG_THAI === 1 &&
               (int)$this->SO_LUONG_DA_DUNG < (int)$this->SO_LUONG_GIOI_HAN &&
               (!$start || $start->lte($now)) &&
               (!$end || $end->gte($now));
    }

    public function voucherKhachHangs()
    {
        return $this->hasMany(VoucherKhachHang::class, 'maVoucher', 'MA_VOUCHER');
    }
}

