<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhuyenMai extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'khuyenmai';
    protected $primaryKey = 'MA_VOUCHER';
    public $incrementing = false;
    protected $keyType = 'string';

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
}
