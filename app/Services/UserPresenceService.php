<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserPresenceService
{
    const TTL_SECONDS = 900; // 15 phút không có thao tác -> offline
    const STORAGE_FILE = 'framework/presence_users.json';

    /**
     * Ghi nhận một người dùng đang online trực tuyến
     */
    public static function record($user, Request $request = null): void
    {
        if (!$user) return;

        $userId = is_object($user) ? (int)($user->id ?? 0) : (int)$user;
        if ($userId <= 0) return;

        $username = is_object($user) ? ($user->username ?? 'User #' . $userId) : ('User #' . $userId);
        
        // Tìm họ tên & vai trò nếu có
        $hoTen = $username;
        $vaiTroId = 6; // default customer
        try {
            if (is_object($user) && isset($user->nguoidung) && $user->nguoidung) {
                $hoTen = $user->nguoidung->hoTen ?: $username;
            } elseif (is_object($user) && method_exists($user, 'nguoidung')) {
                $nd = $user->nguoidung()->first();
                if ($nd && $nd->hoTen) $hoTen = $nd->hoTen;
            }
            if (is_object($user) && isset($user->vaiTroId)) {
                $vaiTroId = (int)$user->vaiTroId;
            }
        } catch (\Throwable $e) {}

        // Xác định Thiết bị (Device)
        $ua = $request ? (string)$request->userAgent() : '';
        $clientHeader = $request ? (string)$request->header('X-Client-Platform', '') : '';
        
        $device = 'Máy tính (Desktop)';
        if ($clientHeader === 'mobile' || str_contains($ua, 'Expo') || str_contains($ua, 'okhttp') || str_contains($ua, 'DolaBakery') || str_contains($ua, 'React-Native')) {
            $device = 'Ứng dụng Mobile';
        } elseif (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua)) {
            $device = 'Trình duyệt Di động';
        }

        // Xác định Hành động hiện tại
        $path = $request ? '/' . ltrim($request->path(), '/') : '';
        $action = self::resolveAction($path, $request ? $request->method() : 'GET');

        $ip = $request ? $request->ip() : '127.0.0.1';

        $users = self::getStorageData();
        $users[$userId] = [
            'id' => $userId,
            'username' => $username,
            'hoTen' => $hoTen,
            'vaiTroId' => $vaiTroId,
            'device' => $device,
            'ip' => $ip,
            'action' => $action,
            'lastActive' => time(),
        ];

        self::saveStorageData($users);
    }

    /**
     * Lấy danh sách tất cả người dùng đang trực tuyến trong vòng TTL_SECONDS
     */
    public static function getOnlineUsers(): array
    {
        $users = self::getStorageData();
        $now = time();
        $activeList = [];
        $cleaned = [];

        foreach ($users as $userId => $info) {
            $last = (int)($info['lastActive'] ?? 0);
            if (($now - $last) <= self::TTL_SECONDS) {
                $cleaned[$userId] = $info;
                $diffMinutes = max(0, round(($now - $last) / 60));
                $timeText = $diffMinutes === 0 ? 'Vừa xong' : ($diffMinutes . ' phút trước');

                $activeList[] = [
                    'id' => $info['id'] ?? $userId,
                    'username' => $info['username'] ?? ('User #' . $userId),
                    'hoTen' => $info['hoTen'] ?? $info['username'] ?? 'Khách',
                    'vaiTroId' => $info['vaiTroId'] ?? 6,
                    'device' => $info['device'] ?? 'Desktop',
                    'ip' => $info['ip'] ?? '127.0.0.1',
                    'action' => $info['action'] ?? 'Đang duyệt trang',
                    'lastActive' => $timeText,
                    'lastActiveTimestamp' => $last,
                ];
            }
        }

        // Lưu lại danh sách đã loại bỏ các session cũ
        if (count($cleaned) !== count($users)) {
            self::saveStorageData($cleaned);
        }

        // Sắp xếp người mới nhất lên trước
        usort($activeList, fn($a, $b) => $b['lastActiveTimestamp'] <=> $a['lastActiveTimestamp']);

        return $activeList;
    }

    /**
     * Đọc từ file storage an toàn
     */
    private static function getStorageData(): array
    {
        $path = storage_path(self::STORAGE_FILE);
        if (!file_exists($path)) {
            return [];
        }

        try {
            $content = @file_get_contents($path);
            if (!$content) return [];
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ghi vào file storage an toàn
     */
    private static function saveStorageData(array $data): void
    {
        try {
            $dir = storage_path('framework');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $path = storage_path(self::STORAGE_FILE);
            @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        } catch (\Throwable $e) {
            Log::warning('Không thể lưu presence data: ' . $e->getMessage());
        }
    }

    /**
     * Phân giải hành động thân thiện từ URL API
     */
    private static function resolveAction(string $path, string $method): string
    {
        if (str_contains($path, '/login')) return 'Đăng nhập hệ thống';
        if (str_contains($path, '/cart/checkout')) return 'Đặt hàng trực tuyến';
        if (str_contains($path, '/cart')) return 'Quản lý giỏ hàng';
        if (str_contains($path, '/products') || str_contains($path, '/san-pham')) return 'Xem danh sách & Chi tiết bánh';
        if (str_contains($path, '/orders') || str_contains($path, '/hoa-don')) return 'Tra cứu hóa đơn & Đơn hàng';
        if (str_contains($path, '/admin/pos')) return 'Bán hàng tại quầy POS';
        if (str_contains($path, '/admin/giam-sat') || str_contains($path, '/admin/system')) return 'Giám sát kỹ thuật máy chủ';
        if (str_contains($path, '/admin/analytics') || str_contains($path, '/admin/bao-cao')) return 'Xem báo cáo phân tích';
        if (str_contains($path, '/chat')) return 'Trò chuyện & Hỗ trợ CSKH';
        if (str_contains($path, '/tin-tuc') || str_contains($path, '/news')) return 'Xem tin tức & Khuyến mãi';
        if (str_contains($path, '/profile') || str_contains($path, '/thong-tin')) return 'Xem hồ sơ cá nhân';
        if (str_contains($path, '/stores') || str_contains($path, '/cua-hang')) return 'Tìm kiếm chi nhánh';
        
        return $method === 'POST' ? 'Gửi yêu cầu hệ thống' : 'Duyệt hệ thống';
    }
}

