<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuTrangThaiDonHang extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'lichSuTrangThaiDonHang';

    protected $fillable = [
        'donHangId',
        'trangThaiCu',
        'trangThaiMoi',
        'ghnStatusCode',
        'moTaChiTiet',
        'nguonCapNhat', // 0: GHN Webhook, 1: Hệ thống, 2: Thủ công
    ];

    protected $casts = [
        'trangThaiCu' => 'integer',
        'trangThaiMoi' => 'integer',
        'nguonCapNhat' => 'integer',
    ];

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'donHangId');
    }
}
