<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemFeatureLock extends Model
{
    use HasFactory;

    protected $table = 'he_thong_khoa_chuc_nang';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'maChucNang',
        'tenChucNang',
        'nhomChucNang',
        'isLocked',
        'lyDoKhoa',
        'updatedBy',
    ];

    protected $casts = [
        'isLocked' => 'boolean',
    ];

    /**
     * Quick check if a feature is locked
     */
    public static function isFeatureLocked(string $maChucNang): bool
    {
        $feature = static::where('maChucNang', $maChucNang)->first();
        return $feature ? (bool)$feature->isLocked : false;
    }
}
