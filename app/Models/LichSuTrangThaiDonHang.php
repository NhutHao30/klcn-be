<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuTrangThaiDonHang extends Model
{
    use HasFactory;

    protected $table = 'lich_su_trang_thai_don_hang';

    protected $fillable = [
        'donhang_id',
        'trang_thai_cu',
        'trang_thai_moi',
        'ghn_status_code',
        'mo_ta_chi_tiet',
        'nguon_cap_nhat', // 0: GHN Webhook, 1: Hệ thống, 2: Thủ công
    ];

    protected $casts = [
        'trang_thai_cu' => 'integer',
        'trang_thai_moi' => 'integer',
        'nguon_cap_nhat' => 'integer',
    ];

    public function donhang()
    {
        return $this->belongsTo(DonHang::class, 'donhang_id');
    }
}
