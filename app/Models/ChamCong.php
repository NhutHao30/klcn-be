<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamCong extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'chamCong';

    public function getTable()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('chamCong')) {
                return 'chamCong';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('chamcong')) {
                return 'chamcong';
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('cham_cong')) {
                return 'cham_cong';
            }
        } catch (\Throwable $e) {}
        return 'chamCong';
    }

    protected $fillable = [
        'USERNAME',
        'NGAYCHAMCONG',
        'TRANGTHAI',
    ];

    public function taikhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'username');
    }

    public function nhanvien()
    {
        return $this->belongsTo(NguoiDung::class, 'USERNAME', 'taiKhoanId');
    }
}
