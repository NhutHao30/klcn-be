<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhaCungCap extends Model
{
    protected $table = 'nha_cung_cap';
    protected $primaryKey = 'MANCC';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'MANCC',
        'TENNCC',
        'DIENTHOAI',
        'EMAIL',
        'DIACHI',
        'NGUOIDAIDIEN',
        'MASOTHUE',
        'TRANGTHAI',
        'GHICHU',
    ];

    /**
     * Danh sách phiếu nhập từ NCC này
     */
    public function hdnhaps()
    {
        return $this->hasMany(HdNhap::class, 'MANCC', 'MANCC');
    }
}
