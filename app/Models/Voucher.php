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

    public function getIsValidAttribute()
    {
        $now = now();
        return $this->TRANG_THAI &&
               $this->SO_LUONG_DA_DUNG < $this->SO_LUONG_GIOI_HAN &&
               $this->NGAY_BAT_DAU <= $now &&
               $this->NGAY_KET_THUC >= $now;
    }

    public function voucherKhachHangs()
    {
        return $this->hasMany(VoucherKhachHang::class, 'maVoucher', 'MA_VOUCHER');
    }
}

