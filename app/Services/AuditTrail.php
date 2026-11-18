<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Ghi Audit Log vào bảng `auditlogs`.
 *
 * - Tự động ghi khi Eloquent model được tạo / sửa / xóa (gọi từ AppServiceProvider).
 * - LogActivityMiddleware ghi bổ sung 1 dòng cấp request nếu request đó không phát sinh
 *   sự kiện model nào (VD: thao tác qua stored procedure, đăng nhập/đăng xuất).
 */
class AuditTrail
{
    /** Số dòng audit đã ghi trong request hiện tại */
    public static int $recordedInRequest = 0;

    /** Tạm tắt ghi log (dùng khi chạy seed/script) */
    public static bool $disabled = false;

    /** Các model không cần audit (bảng kỹ thuật / tần suất cao) */
    protected static array $ignoredModels = [
        \App\Models\AuditLog::class,
        \App\Models\NhatKyHeThong::class,
        \App\Models\IdempotencyKey::class,
        \App\Models\OutboxEvent::class,
        \App\Models\TinNhan::class,
        \App\Models\CuocTroChuyen::class,
    ];

    /** Các trường nhạy cảm không bao giờ lưu vào log */
    protected static array $hiddenFields = [
        'password', 'PASSWORD', 'rememberToken', 'remember_token', 'token',
        'access_token', 'otp', 'OTP_CODE', 'chuKySo', 'digitalSignatureHash',
    ];

    /** Các trường bỏ qua khi so sánh thay đổi */
    protected static array $noiseFields = ['createdAt', 'updatedAt', 'created_at', 'updated_at'];

    public static function currentUser()
    {
        try {
            return Auth::guard('api')->user();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Chỉ audit thao tác của nhân sự nội bộ (không ghi thao tác của khách hàng / khách vãng lai) */
    public static function shouldAuditActor($user): bool
    {
        if (!$user) {
            return false;
        }
        return (int) ($user->vaiTroId ?? 0) !== 6;
    }

    public static function handleModelEvent(string $event, Model $model): void
    {
        if (self::$disabled || app()->runningInConsole() && !app()->runningUnitTests()) {
            return;
        }
        foreach (self::$ignoredModels as $ignored) {
            if ($model instanceof $ignored) {
                return;
            }
        }

        $user = self::currentUser();
        if (!self::shouldAuditActor($user)) {
            return;
        }

        $old = null;
        $new = null;
        $action = 'UPDATE';

        if ($event === 'created') {
            $action = 'INSERT';
            $new = self::clean($model->getAttributes());
        } elseif ($event === 'updated') {
            $changes = array_diff_key($model->getChanges(), array_flip(self::$noiseFields));
            if (empty($changes)) {
                return;
            }
            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
            }
            $old = self::clean($old);
            $new = self::clean($changes);
            $old = ['id' => $model->getKey()] + $old;
            $new = ['id' => $model->getKey()] + $new;
        } elseif ($event === 'deleted') {
            $action = 'DELETE';
            $old = self::clean($model->getOriginal() ?: $model->getAttributes());
        } else {
            return;
        }

        self::write([
            'action' => $action,
            'tableName' => $model->getTable(),
            'entityId' => (string) $model->getKey(),
            'moTa' => self::describe($action, $model),
            'oldValues' => $old,
            'newValues' => $new,
        ], $user);
    }

    /** Ghi log tùy chỉnh cho nghiệp vụ */
    public static function logCustom(string $action, string $tableName, $entityId = null, $oldValues = null, $newValues = null, ?string $moTa = null, $user = null): void
    {
        self::write([
            'action' => $action,
            'tableName' => $tableName,
            'entityId' => (string) $entityId,
            'moTa' => $moTa,
            'oldValues' => $oldValues,
            'newValues' => $newValues,
        ], $user);
    }

    /** Ghi 1 dòng audit */
    public static function write(array $data, $user = null): void
    {
        try {
            $request = request();
            $user = $user ?? self::currentUser();
            $tableName = $data['tableName'] ?? null;

            $row = [
                'taiKhoanId' => $user?->id,
                'action' => substr((string) ($data['action'] ?? 'UPDATE'), 0, 100),
                'tableName' => $tableName ? substr((string) $tableName, 0, 50) : null,
                'oldValues' => isset($data['oldValues']) ? json_encode($data['oldValues'], JSON_UNESCAPED_UNICODE) : null,
                'newValues' => isset($data['newValues']) ? json_encode($data['newValues'], JSON_UNESCAPED_UNICODE) : null,
                'ipAddress' => $request ? substr(self::resolveClientIp($request), 0, 45) : null,
                'userAgent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            ];

            $schema = self::extraColumns();
            if ($schema['moTa']) {
                $row['moTa'] = isset($data['moTa']) ? mb_substr((string) $data['moTa'], 0, 255) : null;
            }
            if ($schema['entityId']) {
                $row['entityId'] = isset($data['entityId']) ? substr((string) $data['entityId'], 0, 64) : null;
            }
            if ($schema['requestId']) {
                $row['requestId'] = $request?->attributes->get('request_id');
            }

            $log = new AuditLog();
            $log->unguard();
            $log->forceFill($row);
            $log->saveQuietly();
            self::$recordedInRequest++;
        } catch (\Throwable $e) {
            Log::error('AuditTrail write failed: ' . $e->getMessage());
        }
    }

    protected static ?array $extraColumnsCache = null;

    public static function resolveClientIp($request): string
    {
        foreach (['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'] as $header) {
            $raw = $request->header($header);
            if (!empty($raw)) {
                $parts = array_map('trim', explode(',', (string) $raw));
                foreach ($parts as $ip) {
                    if ($ip !== '' && !in_array($ip, ['127.0.0.1', '::1'], true)) {
                        return $ip;
                    }
                }
                if (!empty($parts[0])) {
                    return $parts[0];
                }
            }
        }
        return (string) ($request->ip() ?: '127.0.0.1');
    }

    protected static function extraColumns(): array
    {
        if (self::$extraColumnsCache === null) {
            try {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing('auditlogs');
            } catch (\Throwable $e) {
                $cols = [];
            }
            self::$extraColumnsCache = [
                'moTa' => in_array('moTa', $cols),
                'entityId' => in_array('entityId', $cols),
                'requestId' => in_array('requestId', $cols),
            ];
        }
        return self::$extraColumnsCache;
    }

    public static function clean(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (in_array($k, self::$hiddenFields, true)) {
                $out[$k] = '[ẩn]';
                continue;
            }
            if (is_string($v) && strlen($v) > 500) {
                $v = (str_starts_with($v, 'data:') ? '[dữ liệu ảnh/base64]' : mb_substr($v, 0, 500) . '…');
            }
            $out[$k] = $v;
        }
        return $out;
    }

    protected static array $tableLabels = [
        'sanpham' => 'sản phẩm', 'loaisanpham' => 'danh mục', 'donhang' => 'đơn hàng',
        'chitietdonhang' => 'chi tiết đơn hàng', 'taikhoan' => 'tài khoản', 'nguoidung' => 'người dùng',
        'cuahang' => 'chi nhánh', 'voucher' => 'voucher', 'hoadonnhap' => 'phiếu nhập hàng',
        'bienbanhuy' => 'biên bản hủy', 'chukyso' => 'chữ ký số', 'lohang' => 'lô hàng',
        'tonkhochinhanh' => 'tồn kho chi nhánh', 'nhacungcap' => 'nhà cung cấp', 'tintuc' => 'tin tức',
        'danhgiasanpham' => 'đánh giá sản phẩm', 'lichsugiasanpham' => 'lịch sử giá',
        'phieudieuchuyen' => 'phiếu điều chuyển', 'yeucaudieuchuyen' => 'yêu cầu điều chuyển',
    ];

    public static function tableLabel(?string $table): string
    {
        $key = strtolower((string) $table);
        return self::$tableLabels[$key] ?? ($table ?: 'dữ liệu');
    }

    protected static function describe(string $action, Model $model): string
    {
        $verb = ['INSERT' => 'Thêm', 'UPDATE' => 'Cập nhật', 'DELETE' => 'Xóa'][$action] ?? $action;
        $label = self::tableLabel($model->getTable());
        $name = $model->getAttribute('tenSanPham')
            ?? $model->getAttribute('tenLoai')
            ?? $model->getAttribute('maDonHang')
            ?? $model->getAttribute('maHoaDonNhap')
            ?? $model->getAttribute('maBienBan')
            ?? $model->getAttribute('hoTen')
            ?? $model->getAttribute('username')
            ?? $model->getAttribute('tieuDe')
            ?? null;
        return trim("{$verb} {$label} #{$model->getKey()}" . ($name ? " ({$name})" : ''));
    }
}

