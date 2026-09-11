<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoHang extends Model
{
    protected $table = 'lo_hang';
    protected $primaryKey = 'MALOHAN';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MALOHAN',
        'MAHDNHAP',
        'MASP',
        'MACUAHANG',
        'NGAYNHAP',
        'NGAYSX',
        'HSD',
        'SOLUONG_NHAP',
        'SOLUONG_CON',
        'SOLUONG_BAN',
        'SOLUONG_HUY',
        'DONGIA_NHAP',
        'TRANGTHAI',
        'GHICHU',
    ];

    protected $casts = [
        'NGAYNHAP'     => 'date',
        'NGAYSX'       => 'date',
        'HSD'          => 'date',
        'SOLUONG_NHAP' => 'integer',
        'SOLUONG_CON'  => 'integer',
        'SOLUONG_BAN'  => 'integer',
        'SOLUONG_HUY'  => 'integer',
        'DONGIA_NHAP'  => 'decimal:2',
    ];

    // ─────────────────────────── Relationships ───────────────────────────

    public function sanpham()
    {
        return $this->belongsTo(SanPham::class, 'MASP', 'MASP');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'MACUAHANG', 'MACUAHANG');
    }

    public function hdnhap()
    {
        return $this->belongsTo(HdNhap::class, 'MAHDNHAP', 'MAHDNHAP');
    }

    public function bienBanHuys()
    {
        return $this->hasMany(BienBanHuy::class, 'MALOHAN', 'MALOHAN');
    }

    public function chiTietHdBans()
    {
        return $this->hasMany(ChiTietHdBan::class, 'MALOHAN', 'MALOHAN');
    }

    // ─────────────────────────── Scopes ───────────────────────────────────

    /**
     * Lô còn hạn và còn hàng để bán.
     */
    public function scopeConHan($query)
    {
        return $query->where('TRANGTHAI', 'con_hang')
                     ->where('HSD', '>=', Carbon::today())
                     ->where('SOLUONG_CON', '>', 0);
    }

    /**
     * Lô sắp hết hạn trong $days ngày tới (tính từ hôm nay).
     */
    public function scopeSapHetHan($query, int $days = 3)
    {
        return $query->where('TRANGTHAI', 'con_hang')
                     ->where('HSD', '>=', Carbon::today())
                     ->where('HSD', '<=', Carbon::today()->addDays($days))
                     ->where('SOLUONG_CON', '>', 0);
    }

    /**
     * Lô đã hết hạn (HSD < hôm nay) nhưng chưa được xử lý.
     */
    public function scopeHetHan($query)
    {
        return $query->where('HSD', '<', Carbon::today())
                     ->where('TRANGTHAI', 'con_hang');
    }

    /**
     * Filter theo cửa hàng.
     */
    public function scopeCuaHang($query, $maCuaHang)
    {
        return $query->where('MACUAHANG', $maCuaHang);
    }

    /**
     * Filter theo sản phẩm.
     */
    public function scopeSanPham($query, $maSP)
    {
        return $query->where('MASP', $maSP);
    }

    // ─────────────────────────── Helpers ──────────────────────────────────

    /**
     * Số ngày còn lại đến HSD (âm = đã hết hạn).
     */
    public function getSoNgayConLaiAttribute(): int
    {
        return (int) Carbon::today()->diffInDays($this->HSD, false);
    }

    /**
     * Mức cảnh báo: 'het_han' | 'nguy_hiem' (≤1 ngày) | 'canh_bao' (≤3 ngày) | 'binh_thuong'
     */
    public function getMucCanhBaoAttribute(): string
    {
        $days = $this->so_ngay_con_lai;
        if ($this->TRANGTHAI === 'het_han' || $days < 0) return 'het_han';
        if ($days <= 1) return 'nguy_hiem';
        if ($days <= 3) return 'canh_bao';
        return 'binh_thuong';
    }

    /**
     * Tổng chi phí lô = số lượng nhập × đơn giá nhập.
     */
    public function getTongChiPhiAttribute(): float
    {
        return $this->SOLUONG_NHAP * $this->DONGIA_NHAP;
    }

    /**
     * % đã bán = bán / nhập * 100.
     */
    public function getPhanTramBanAttribute(): float
    {
        if ($this->SOLUONG_NHAP == 0) return 0;
        return round(($this->SOLUONG_BAN / $this->SOLUONG_NHAP) * 100, 1);
    }
}
