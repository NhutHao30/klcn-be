<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhaCungCap extends Model
{
    use HasFactory;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';


    protected $table = 'nhaCungCap';

    protected $fillable = [
        'tenNcc',
        'soDienThoai',
        'email',
        'dia_chi',
        'trangThai',
    ];

    protected $appends = ['MANCC', 'TENNCC', 'DIENTHOAI', 'EMAIL', 'DIACHI'];

    public function getMANCCAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getTENNCCAttribute()
    {
        return $this->attributes['tenNcc'] ?? null;
    }

    public function getDIENTHOAIAttribute()
    {
        return $this->attributes['soDienThoai'] ?? null;
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
        return $this->hasMany(LoHang::class, 'nhaCungCapId');
    }

    public function hoaDonNhaps()
    {
        return $this->hasMany(HoaDonNhap::class, 'nhaCungCapId');
    }
}
