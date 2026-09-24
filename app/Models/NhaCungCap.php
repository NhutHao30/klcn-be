<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhaCungCap extends Model
{
    use HasFactory;

    protected $table = 'nha_cung_cap';

    protected $fillable = [
        'ten_ncc',
        'so_dien_thoai',
        'email',
        'dia_chi',
        'trang_thai',
    ];

    protected $appends = ['MANCC', 'TENNCC', 'DIENTHOAI', 'EMAIL', 'DIACHI'];

    public function getMANCCAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENNCCAttribute()
    {
        return $this->attributes['ten_ncc'] ?? null;
    }

    public function getDIENTHOAIAttribute()
    {
        return $this->attributes['so_dien_thoai'] ?? null;
    }

    public function getEMAILAttribute()
    {
        return $this->attributes['email'] ?? null;
    }

    public function getDIACHIAttribute()
    {
        return $this->attributes['dia_chi'] ?? null;
    }

    public function loHangs()
    {
        return $this->hasMany(LoHang::class, 'nhacungcap_id');
    }

    public function hoaDonNhaps()
    {
        return $this->hasMany(HoaDonNhap::class, 'nhacungcap_id');
    }
}
