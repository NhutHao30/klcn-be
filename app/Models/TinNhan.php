<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TinNhan extends Model
{
    protected $table = 'tinNhan';
    protected $primaryKey = 'id';

    public function getTable()
    {
        try {
            if (Schema::hasTable('tinNhan')) {
                return 'tinNhan';
            }
            if (Schema::hasTable('tin_nhan')) {
                return 'tin_nhan';
            }
            if (Schema::hasTable('tinnhan')) {
                return 'tinnhan';
            }
        } catch (\Throwable $e) {}
        return 'tinNhan';
    }

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

    public static function getCuocTroChuyenCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'cuocTroChuyenId')) {
                return 'cuocTroChuyenId';
            }
            if (Schema::hasColumn($tbl, 'cuoc_tro_chuyen_id')) {
                return 'cuoc_tro_chuyen_id';
            }
        } catch (\Throwable $e) {}
        return 'cuocTroChuyenId';
    }

    public static function getNguoiGuiTypeCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'nguoiGuiType')) {
                return 'nguoiGuiType';
            }
            if (Schema::hasColumn($tbl, 'nguoi_gui_type')) {
                return 'nguoi_gui_type';
            }
        } catch (\Throwable $e) {}
        return 'nguoiGuiType';
    }

    public static function getNoiDungCol(): string
    {
        try {
            $tbl = (new static)->getTable();
            if (Schema::hasColumn($tbl, 'noiDung')) {
                return 'noiDung';
            }
            if (Schema::hasColumn($tbl, 'noi_dung')) {
                return 'noi_dung';
            }
        } catch (\Throwable $e) {}
        return 'noiDung';
    }

    protected $fillable = [
        'cuocTroChuyenId',
        'cuoc_tro_chuyen_id',
        'nguoiGuiType',
        'nguoi_gui_type',
        'noiDung',
        'noi_dung',
        'daDoc',
        'daThuHoi',
        'channel',
        'taiKhoanId',
    ];

    protected $appends = [
        'MATINNHAN',
        'MACUOCTROCHUYEN',
        'LOAINGUOIGUI',
        'NOIDUNG',
        'NGUOIGUI_ID',
        'DADOC',
        'daDoc',
        'created_at',
        'IS_BOT',
        'isBot',
    ];

    public function setNguoiGuiTypeAttribute($val)
    {
        $code = 0;
        if ($val === 'bot' || $val === 2 || $val === '2') {
            $code = 2;
        } elseif ($val === 'nhanvien' || $val === 1 || $val === '1') {
            $code = 1;
        }
        $this->attributes['nguoiGuiType'] = $code;
        $this->attributes['nguoi_gui_type'] = $code;
    }

    public function getMATINNHANAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getMACUOCTROCHUYENAttribute()
    {
        return $this->attributes['cuocTroChuyenId'] ?? $this->attributes['cuoc_tro_chuyen_id'] ?? null;
    }

    public function getLOAINGUOIGUIAttribute()
    {
        $type = (int)($this->attributes['nguoiGuiType'] ?? $this->attributes['nguoi_gui_type'] ?? 0);
        if ($type === 2) {
            return 'bot';
        }
        return ($type === 1) ? 'nhanvien' : 'khachhang';
    }

    public function getNOIDUNGAttribute()
    {
        return $this->attributes['noiDung'] ?? $this->attributes['noi_dung'] ?? '';
    }

    public function getNguoiGuiIdAttribute()
    {
        return $this->getLOAINGUOIGUIAttribute();
    }

    public function getIsBotAttribute()
    {
        $type = (int)($this->attributes['nguoiGuiType'] ?? $this->attributes['nguoi_gui_type'] ?? 0);
        return $type === 2;
    }

    public function getDADOCAttribute()
    {
        return (int)($this->attributes['daDoc'] ?? 0);
    }

    public function getCreatedAtAttribute()
    {
        return $this->attributes['createdAt'] ?? $this->attributes['created_at'] ?? now()->toIso8601String();
    }

    public function cuoctrochuyen()
    {
        return $this->belongsTo(CuocTroChuyen::class, static::getCuocTroChuyenCol(), 'id');
    }
}
