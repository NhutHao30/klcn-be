<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaChi extends Model
{
    protected $table = 'diachi';
    // primary key defaults to 'id'

    protected $fillable = [
        'MAKH',
        'DIACHI',
        'SONHA',
        'DUONG',
        'PHUONGXA',
        'QUANHUYEN',
        'TINHTP',
        'LOAI',
        'MACDINH',
    ];

    public function khachhang()
    {
        return $this->belongsTo(KhachHang::class, 'MAKH', 'MAKH');
    }

    /**
     * Helper accessor trả về chuỗi địa chỉ đầy đủ ghép từ các thành phần rã
     */
    public function getLayDiaChiDayDuAttribute()
    {
        if ($this->SONHA || $this->DUONG || $this->PHUONGXA || $this->QUANHUYEN || $this->TINHTP) {
            $parts = array_filter([
                $this->SONHA,
                $this->DUONG,
                $this->PHUONGXA,
                $this->QUANHUYEN,
                $this->TINHTP
            ]);
            return implode(', ', $parts);
        }
        return $this->DIACHI;
    }
}
