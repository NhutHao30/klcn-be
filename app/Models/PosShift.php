<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    protected $fillable = [
        'MACUAHANG',
        'USERNAME',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash_expected',
        'closing_cash_actual',
        'status',
        'note',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // cash_difference is missing because it's computed in the view, not in the table
    // But we can retrieve it if we query the view

    public function cuahang()
    {
        return $this->belongsTo(CuaHang::class, 'MACUAHANG', 'MACUAHANG');
    }

    public function user()
    {
        return $this->belongsTo(TaiKhoan::class, 'USERNAME', 'USERNAME');
    }
}
