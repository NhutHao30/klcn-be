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
    protected $primaryKey = 'MADG';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'MASP',
        'MAKH',
        'MAHD',
        'SO_SAO',
        'NOI_DUNG',
        'HINH_ANH',
        'NGAY_DG',
        'TRANG_THAI',
    ];

    public $timestamps = true;

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'MASP', 'MASP');
    }

    public function khachhang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }

    public function hdban()
    {
        return $this->belongsTo(HdBan::class, 'MAHD', 'MAHD');
    }
}
