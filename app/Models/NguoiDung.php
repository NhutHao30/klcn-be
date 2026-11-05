<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NguoiDung extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'nguoidung';

    protected $fillable = [
        'taiKhoanId',
        'cuaHangId',
        'hoTen',
        'soDienThoai',
        'gioiTinh', // 0: Nữ, 1: Nam, 2: Khác
        'ngaySinh',
        'cccdNumber',
        'cccdImageUrl',
        'loaiNguoiDung', // 0: Khách hàng, 1: Nhân viên
        'diemTichLuy',
        'tongChiTieu',
        'hangThanhVien',
        'avatar',
    ];

    protected $casts = [
        'gioiTinh' => 'integer',
        'loaiNguoiDung' => 'integer',
        'ngaySinh' => 'date',
        'diemTichLuy' => 'integer',
        'tongChiTieu' => 'double',
    ];

    protected $appends = [
        'ho_ten',
        'HOTEN',
        'SDT',
        'GioiTinh',
        'NGAYSINH',
        'DIACHI',
        'MAKH',
        'MANV',
        'DIEMTICHLUY',
        'HANGTHANHVIEN',
        'TONGCHITIEU',
        'MACUAHANG',
        'avatar',
        'AVATAR',
    ];

    public static function resolveRankByPoints(int $points): string
    {
        if ($points >= 1000) return 'Thách đấu';
        if ($points >= 500) return 'Kim cương';
        if ($points >= 200) return 'Vàng';
        if ($points >= 50) return 'Bạc';
        return 'Đồng';
    }

    /**
     * Đồng bộ điểm tích lũy (10.000đ = 1 điểm) và hạng thành viên từ các đơn hàng đã hoàn thành
     */
    public function syncLoyaltyPoints(): self
    {
        if (!$this->id) {
            return $this;
        }

        $orderTable = \Illuminate\Support\Facades\Schema::hasTable('donHang') ? 'donHang' : 'donhang';
        $totalSpent = (float) DB::table($orderTable)
            ->where('nguoidungId', $this->id)
            ->whereIn('trangThaiDonHang', [4, 5])
            ->sum('tongThanhToan');

        $calculatedPoints = (int) floor($totalSpent / 10000);
        // Nếu quản trị viên đã cộng thêm điểm thưởng thủ công cao hơn mức tự động, giữ mức cao hơn
        $currentPoints = (int) ($this->attributes['diemTichLuy'] ?? 0);
        $finalPoints = max($calculatedPoints, $currentPoints);
        $rank = self::resolveRankByPoints($finalPoints);

        if (
            $finalPoints !== $currentPoints ||
            abs($totalSpent - (float)($this->attributes['tongChiTieu'] ?? 0)) > 0.01 ||
            ($this->attributes['hangThanhVien'] ?? '') !== $rank
        ) {
            DB::table('nguoidung')->where('id', $this->id)->update([
                'diemTichLuy' => $finalPoints,
                'tongChiTieu' => $totalSpent,
                'hangThanhVien' => $rank,
            ]);
            $this->attributes['diemTichLuy'] = $finalPoints;
            $this->attributes['tongChiTieu'] = $totalSpent;
            $this->attributes['hangThanhVien'] = $rank;
        }

        return $this;
    }

    public function getHOTENAttribute()
    {
        return $this->attributes['hoTen'] ?? null;
    }

    public function getAvatarAttribute()
    {
        return $this->attributes['avatar'] ?? $this->taikhoan?->avatar ?? null;
    }

    public function getSDTAttribute()
    {
        return $this->attributes['soDienThoai'] ?? null;
    }

    public function getGioiTinhAttribute()
    {
        $val = $this->attributes['gioiTinh'] ?? null;
        if ((int)$val === 1) return 'Nam';
        if ((int)$val === 0 && !is_null($val)) return 'Nữ';
        return 'Khác';
    }

    public function getNGAYSINHAttribute()
    {
        $val = $this->attributes['ngaySinh'] ?? null;
        if (!$val) return null;
        return is_string($val) ? substr($val, 0, 10) : (method_exists($val, 'format') ? $val->format('Y-m-d') : (string)$val);
    }

    public function getDIACHIAttribute()
    {
        if ($this->relationLoaded('diachi')) {
            return $this->diachi->first()?->diaChiChiTiet ?? '';
        }
        return '';
    }

    public function getMAKHAttribute()
    {
        return 'KH_' . sprintf('%05d', $this->attributes['id'] ?? $this->id ?? 0);
    }

    public function getMANVAttribute()
    {
        return 'NV_' . sprintf('%05d', $this->attributes['id'] ?? $this->id ?? 0);
    }

    public function getDIEMTICHLUYAttribute()
    {
        return (int) ($this->attributes['diemTichLuy'] ?? $this->attributes['diem_tich_luy'] ?? 0);
    }

    public function getHANGTHANHVIENAttribute()
    {
        $pts = $this->getDIEMTICHLUYAttribute();
        return $this->attributes['hangThanhVien'] ?? self::resolveRankByPoints($pts);
    }

    public function getTONGCHITIEUAttribute()
    {
        return (float) ($this->attributes['tongChiTieu'] ?? 0);
    }

    public function getMACUAHANGAttribute()
    {
        return $this->attributes['cuaHangId'] ?? 1;
    }

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taiKhoanId');
    }

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'cuaHangId');
    }

    public function diachi()
    {
        return $this->hasMany(DiaChi::class, 'nguoidungId');
    }

    public function donhang()
    {
        return $this->hasMany(DonHang::class, 'nguoidungId');
    }

    public function giohang()
    {
        return $this->hasOne(GioHang::class, 'nguoidungId');
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class, 'nguoidungId');
    }

    public function vouchers()
    {
        return $this->hasMany(VoucherKhachHang::class, 'nguoidungId');
    }
}
