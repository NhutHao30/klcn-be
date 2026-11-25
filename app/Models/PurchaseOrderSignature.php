<?php

namespace App\Models;

use App\Enums\PurchaseOrderSignatureRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderSignature extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_signatures';

    protected $fillable = [
        'hoaDonNhapId',
        'vaiTroKy',
        'nguoiKyId',
        'tenNguoiKy',
        'chuKyAnh',
        'ghiChu',
        'thoiGianKy',
    ];

    protected $casts = [
        'thoiGianKy' => 'datetime',
    ];

    public function hoaDonNhap()
    {
        return $this->belongsTo(HoaDonNhap::class, 'hoaDonNhapId');
    }

    public function nguoiKy()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoiKyId');
    }

    public function roleEnum(): ?PurchaseOrderSignatureRole
    {
        return PurchaseOrderSignatureRole::tryFrom((string) $this->vaiTroKy);
    }
}
