<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemErrorLog extends Model
{
    use HasFactory;

    protected $table = 'he_thong_loi';

    public function getCreatedAtColumn()
    {
        try {
            if (Schema::hasColumn($this->getTable(), 'createdAt')) {
                return 'createdAt';
            }
        } catch (\Throwable $e) {}
        return 'created_at';
    }

    public function getUpdatedAtColumn()
    {
        try {
            if (Schema::hasColumn($this->getTable(), 'updatedAt')) {
                return 'updatedAt';
            }
        } catch (\Throwable $e) {}
        return 'updated_at';
    }

    protected $fillable = [
        'maLoi',
        'loaiLoi',
        'tieuDeLoi',
        'tenLoi',
        'thongDiep',
        'file',
        'tepTin',
        'dong',
        'dongSo',
        'stackTrace',
        'url',
        'soLanXuatHien',
        'mucDo',
        'lanCuoiXuatHien',
        'trangThai',
    ];

    protected $appends = [
        'tenLoi',
        'thongDiep',
        'tepTin',
        'dongSo',
    ];

    protected $casts = [
        'soLanXuatHien' => 'integer',
        'trangThai' => 'integer',
        'dong' => 'integer',
        'lanCuoiXuatHien' => 'datetime',
    ];

    public function setTenLoiAttribute($val)
    {
        if (empty($this->attributes['tieuDeLoi'])) {
            $this->attributes['tieuDeLoi'] = $val;
        }
    }

    public function setThongDiepAttribute($val)
    {
        if (empty($this->attributes['tieuDeLoi'])) {
            $this->attributes['tieuDeLoi'] = $val;
        }
    }

    public function setTepTinAttribute($val)
    {
        if (empty($this->attributes['file'])) {
            $this->attributes['file'] = $val;
        }
    }

    public function setDongSoAttribute($val)
    {
        if (!isset($this->attributes['dong'])) {
            $this->attributes['dong'] = (int)$val;
        }
    }

    public function getTenLoiAttribute()
    {
        return $this->attributes['tieuDeLoi'] ?? 'Lỗi không xác định';
    }

    public function getThongDiepAttribute()
    {
        return $this->attributes['stackTrace'] ? substr($this->attributes['stackTrace'], 0, 150) : ($this->attributes['tieuDeLoi'] ?? '');
    }

    public function getTepTinAttribute()
    {
        return $this->attributes['file'] ?? '';
    }

    public function getDongSoAttribute()
    {
        return (int)($this->attributes['dong'] ?? 0);
    }
}
