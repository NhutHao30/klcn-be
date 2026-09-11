<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SanPham extends Model
{
    use SoftDeletes;

    protected $table = 'sanpham';
    protected $primaryKey = 'MASP';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MASP',
        'TENSP',
        'NSX',
        'HSD',
        'DVT',
        'GIABAN',
        'SOLUONG',
        'GHICHU',
        'HINHANH',
        'MALOAI',
        'IS_NEW',
        'PHAN_TRAM_GIAM',
    ];

    public function loaisanpham()
    {
        return $this->belongsTo(LoaiSanPham::class, 'MALOAI', 'MALOAI');
    }

    public function giamgia()
    {
        return $this->hasOne(GiamGia::class, 'MASP', 'MASP');
    }

    public function chitiethdnhaps()
    {
        return $this->hasMany(ChiTietHdNhap::class, 'MASP', 'MASP');
    }

    public function chitiethdbans()
    {
        return $this->hasMany(ChiTietHdBan::class, 'MASP', 'MASP');
    }

    public function chitietgiohangs()
    {
        return $this->hasMany(ChiTietGioHang::class, 'MASP', 'MASP');
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'MASP', 'MASP');
    }

    public function loHangsConHan()
    {
        return $this->hasMany(LoHang::class, 'MASP', 'MASP')
                    ->where('TRANGTHAI', 'con_hang')
                    ->where('HSD', '>=', now()->toDateString())
                    ->where('SOLUONG_CON', '>', 0);
    }

    public function lichSuGias()
    {
        return $this->hasMany(LichSuGiaSanPham::class, 'MASP', 'MASP')->orderBy('created_at', 'desc');
    }

    protected static function booted()
    {
        static::created(function ($sanPham) {
            $user = auth('api')->user();
            LichSuGiaSanPham::create([
                'MASP' => $sanPham->MASP,
                'GIA_CU' => null,
                'GIA_MOI' => $sanPham->GIABAN,
                'PHAN_TRAM_GIAM' => $sanPham->PHAN_TRAM_GIAM ?? 0,
                'LY_DO' => 'Khởi tạo sản phẩm mới',
                'USERNAME_NV' => $user ? $user->USERNAME : null,
                'NGAY_AP_DUNG' => now(),
            ]);
        });

        static::updated(function ($sanPham) {
            if ($sanPham->isDirty('GIABAN') || $sanPham->isDirty('PHAN_TRAM_GIAM')) {
                $user = auth('api')->user();
                $giaCu = $sanPham->getOriginal('GIABAN');
                $giaMoi = $sanPham->GIABAN;
                $phanTramCu = $sanPham->getOriginal('PHAN_TRAM_GIAM') ?? 0;
                $phanTramMoi = $sanPham->PHAN_TRAM_GIAM ?? 0;

                $lyDo = 'Điều chỉnh giá niêm yết';
                if ($sanPham->isDirty('PHAN_TRAM_GIAM')) {
                    $lyDo = "Cập nhật % giảm giá từ {$phanTramCu}% thành {$phanTramMoi}%";
                }
                if ($sanPham->isDirty('GIABAN') && $sanPham->isDirty('PHAN_TRAM_GIAM')) {
                    $lyDo = "Cập nhật giá bán ({$giaCu} -> {$giaMoi}) & % giảm ({$phanTramCu}% -> {$phanTramMoi}%)";
                }

                LichSuGiaSanPham::create([
                    'MASP' => $sanPham->MASP,
                    'GIA_CU' => $giaCu,
                    'GIA_MOI' => $giaMoi,
                    'PHAN_TRAM_GIAM' => $phanTramMoi,
                    'LY_DO' => $lyDo,
                    'USERNAME_NV' => $user ? $user->USERNAME : null,
                    'NGAY_AP_DUNG' => now(),
                ]);
            }
        });
    }
}
