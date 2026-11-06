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
        'chuKySo',
    ];

    protected $casts = [
        'gioiTinh' => 'integer',
        'loaiNguoiDung' => 'integer',
        'ngaySinh' => 'date:Y-m-d',
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
        'chuKySo',
        'chuKy',
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
        if (!empty($this->attributes['avatar'])) {
            return $this->attributes['avatar'];
        }
        if ($this->relationLoaded('taikhoan') && !empty($this->taikhoan?->attributes['avatar'])) {
            return $this->taikhoan->attributes['avatar'];
        }
        return null;
    }

    public function getChuKySoAttribute()
    {
        if (!empty($this->attributes['chuKySo'])) {
            return $this->attributes['chuKySo'];
        }
        if ($this->relationLoaded('taikhoan') && !empty($this->taikhoan?->attributes['chuKySo'])) {
            return $this->taikhoan->attributes['chuKySo'];
        }
        return null;
    }

    public function getChuKyAttribute()
    {
        return $this->getChuKySoAttribute();
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

    public function getNgaySinhAttribute($val)
    {
        $v = $val ?? ($this->attributes['ngaySinh'] ?? null);
        if (!$v) return null;
        if (is_string($v)) {
            return substr($v, 0, 10);
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        return (string) $v;
    }

    public function setNgaySinhAttribute($val)
    {
        if (empty($val)) {
            $this->attributes['ngaySinh'] = null;
            return;
        }
        if (is_string($val)) {
            $val = trim($val);
            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $m)) {
                $this->attributes['ngaySinh'] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                return;
            }
            if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $val, $m)) {
                $this->attributes['ngaySinh'] = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                return;
            }
        }
        $this->attributes['ngaySinh'] = $val;
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
