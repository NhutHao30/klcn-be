<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BienBanHuy extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'bienBanHuy';

    protected $fillable = [
        'maBienBan',
        'ma_bien_ban',
        'cuaHangId',
        'cuahang_id',
        'nguoiLapId',
        'nguoi_lap_id',
        'ngayLap',
        'ngay_lap',
        'lyDo',
        'ly_do',
        'tongGiaTriHuy',
        'tong_gia_tri_huy',
        'trangThai',
        'trang_thai',
    ];

    protected $casts = [
        'ngay_lap' => 'datetime',
        'tong_gia_tri_huy' => 'double',
    ];

    protected $appends = [
        'MABB', 'MABIENBAN', 'MABBHUY', 'MACUAHANG', 'NGAY_HUY', 'NGAYLAP',
        'LYDO_HUY', 'LYDO', 'TONGGIATRI_HUY', 'TRANGTHAI',
        'MALOHAN', 'TENSP', 'SOLUONG_HUY', 'NGUOI_HUY', 'ly_do_label'
    ];

    public function getMABIENBANAttribute()
    {
        return $this->attributes['maBienBan'] ?? $this->attributes['ma_bien_ban'] ?? ('BBH' . str_pad($this->attributes['id'] ?? '', 6, '0', STR_PAD_LEFT));
    }

    public function getMABBAttribute()
    {
        return $this->getMABIENBANAttribute();
    }

    public function getMABBHUYAttribute()
    {
        return $this->getMABIENBANAttribute();
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuaHangId'] ?? $this->attributes['cua_hang_id'] ?? null;
    }

    public function getNGAYHUYAttribute()
    {
        return $this->attributes['ngayLap'] ?? $this->attributes['ngay_lap'] ?? $this->attributes['createdAt'] ?? null;
    }

    public function getNGAYLAPAttribute()
    {
        return $this->getNGAYHUYAttribute();
    }

    public function getLYDOHUYAttribute()
    {
        return $this->attributes['lyDo'] ?? $this->attributes['ly_do'] ?? null;
    }

    public function getLYDOAttribute()
    {
        return $this->getLYDOHUYAttribute();
    }

    public function getLyDoLabelAttribute()
    {
        $code = $this->getLYDOHUYAttribute();
        return match ($code) {
            'het_han' => 'Hết hạn sử dụng (FEFO)',
            'hong_hong' => 'Hư hỏng trong bảo quản',
            'thu_hoi' => 'Thu hồi từ Nhà cung cấp',
            'khac' => 'Lý do khác',
            default => $code ?: 'Tiêu hủy định kỳ',
        };
    }

    public function getTONGGIATRIHUYAttribute()
    {
        return (double) ($this->attributes['tongGiaTriHuy'] ?? $this->attributes['tong_gia_tri_huy'] ?? 0);
    }

    public function getTRANGTHAIAttribute()
    {
        return (int) ($this->attributes['trangThai'] ?? 0);
    }

    public function getMALOHANAttribute()
    {
        $first = $this->relationLoaded('chiTiets') ? $this->chiTiets->first() : $this->chiTiets()->with('loHang')->first();
        return $first?->loHang?->maLoHang ?? $first?->lo_hang?->maLoHang ?? null;
    }

    public function getTENSPAttribute()
    {
        $first = $this->relationLoaded('chiTiets') ? $this->chiTiets->first() : $this->chiTiets()->with('sanPham')->first();
        return $first?->sanPham?->tenSanPham ?? $first?->san_pham?->tenSanPham ?? null;
    }

    public function getSOLUONGHUYAttribute()
    {
        if ($this->relationLoaded('chiTiets')) {
            return (int) $this->chiTiets->sum('soLuongHuy');
        }
        return (int) $this->chiTiets()->sum('soLuongHuy');
    }

    public function getNGUOIHUYAttribute()
    {
        $nl = $this->relationLoaded('nguoiLap') ? $this->nguoiLap : $this->nguoiLap()->first();
        return $nl?->hoTen ?? $nl?->HOTEN ?? 'Nhân viên kho';
    }

    public function cuaHang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function nguoiLap()
    {
        return $this->belongsTo(NguoiDung::class, 'nguoiLapId');
    }

    public function chuKySos()
    {
        return $this->hasMany(ChuKySo::class, 'taiLieuId')->where('loaiTaiLieu', 2);
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietBienBanHuy::class, 'bienBanHuyId');
    }

    /**
     * Biên bản hủy yêu cầu đủ 2 chữ ký: Kế toán (1), Thủ kho (2).
     */
    public function kiemTraDaKyDu(): bool
    {
        $signedRolesCount = $this->chuKySos()->where('trangThai', 1)->distinct('vaiTroKy')->count('vaiTroKy');
        return $signedRolesCount >= 2;
    }
}
