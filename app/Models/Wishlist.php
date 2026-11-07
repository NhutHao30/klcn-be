<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Wishlist extends Model
{
    use HasFactory;

    protected $table = 'wishlist';
    protected $guarded = [];

    /**
     * Tự động thích ứng tên cột timestamps
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        try {
            if (Schema::hasColumn('wishlist', 'createdAt')) {
                $this->timestamps = true;
                defined('static::CREATED_AT') ?: null;
            } elseif (!Schema::hasColumn('wishlist', 'created_at')) {
                $this->timestamps = false;
            }
        } catch (\Throwable $e) {
            $this->timestamps = false;
        }
    }

    public function nguoidung()
    {
        $foreignKey = 'nguoidungId';
        try {
            if (Schema::hasColumn('wishlist', 'nguoidung_id')) {
                $foreignKey = 'nguoidung_id';
            }
        } catch (\Throwable $e) {}

        return $this->belongsTo(NguoiDung::class, $foreignKey);
    }

    public function sanpham()
    {
        $foreignKey = 'sanPhamId';
        try {
            if (Schema::hasColumn('wishlist', 'sanpham_id')) {
                $foreignKey = 'sanpham_id';
            }
        } catch (\Throwable $e) {}

        return $this->belongsTo(SanPham::class, $foreignKey);
    }
}
