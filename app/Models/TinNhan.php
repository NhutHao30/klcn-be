<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinNhan extends Model
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'tinnhan';
    protected $primaryKey = 'id';

    protected $fillable = [
        'cuocTroChuyenId',
        'nguoiGuiType',
        'noiDung',
        'daDoc',
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
        if ($val === 'bot' || $val === 2 || $val === '2') {
            $this->attributes['nguoiGuiType'] = 2;
        } elseif ($val === 'nhanvien' || $val === 1 || $val === '1') {
            $this->attributes['nguoiGuiType'] = 1;
        } else {
            $this->attributes['nguoiGuiType'] = 0;
        }
    }

    public function getMATINNHANAttribute()
    {
        return $this->attributes['id'] ?? null;
    }

    public function getMACUOCTROCHUYENAttribute()
    {
        return $this->attributes['cuocTroChuyenId'] ?? null;
    }

    public function getLOAINGUOIGUIAttribute()
    {
        $type = (int)($this->attributes['nguoiGuiType'] ?? 0);
        if ($type === 2) {
            return 'bot';
        }
        return ($type === 1) ? 'nhanvien' : 'khachhang';
    }

    public function getNOIDUNGAttribute()
    {
        return $this->attributes['noiDung'] ?? '';
    }

    public function getNguoiGuiIdAttribute()
    {
        $type = (int)($this->attributes['nguoiGuiType'] ?? 0);
        if ($type === 2) {
            return 'bot';
        }
        return ($type === 1) ? 'nhanvien' : 'khachhang';
    }

    public function getIsBotAttribute()
    {
        return ((int)($this->attributes['nguoiGuiType'] ?? 0)) === 2;
    }

    public function getDADOCAttribute()
    {
        return (int)($this->attributes['daDoc'] ?? 0);
    }


    public function getCreatedAtAttribute()
    {
        return $this->attributes['createdAt'] ?? now()->toIso8601String();
    }

    public function cuoctrochuyen()
    {
        return $this->belongsTo(CuocTroChuyen::class, 'cuocTroChuyenId', 'id');
    }
}
