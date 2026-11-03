<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CuocTroChuyen;
use App\Models\TinNhan;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\NguoiDung;
use App\Events\MessageSent;
use App\Events\InternalMessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Lấy danh sách cuộc trò chuyện
     */
    public function getConversations(Request $request)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        if ($userLogin->MAROLE == 3) {
            // Khách hàng -> Lấy danh sách đoạn chat của họ
            $khachHang = KhachHang::findByUsername($userLogin->username) ?? NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            if (!$khachHang) {
                return response()->json(['error' => 'Không tìm thấy hồ sơ khách hàng'], 404);
            }

            $conversations = CuocTroChuyen::with(['tinnhans' => function ($q) {
                $q->orderBy('id', 'desc')->take(1);
            }, 'khachhang'])
            ->where('khachHangId', $khachHang->id)
            ->orderBy('updatedAt', 'desc')
            ->get();

            return response()->json($conversations);

        } else {
            // Nhân viên / Admin / CSKH -> Ghi nhận đang trực tuyến & Lấy tất cả đoạn chat
            \App\Services\StaffPresenceService::recordOnline((int)$userLogin->id);

            $conversations = CuocTroChuyen::with(['tinnhans' => function ($q) {
                $q->orderBy('id', 'desc')->take(1);
            }, 'khachhang'])
            ->orderBy('updatedAt', 'desc')
            ->get();

            return response()->json($conversations);
        }
    }

    /**
     * Khách hàng tạo hoặc lấy đoạn chat
     */
    public function startConversation(Request $request)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Vui lòng đăng nhập để bắt đầu trò chuyện'], 401);
        }

        $khachHang = KhachHang::findByUsername($userLogin->username) ?? NguoiDung::where('taiKhoanId', $userLogin->id)->first();
        if (!$khachHang) {
            // Tự động tạo hồ sơ khách hàng nếu tài khoản chưa có bản ghi nguoidung
            $khachHang = NguoiDung::create([
                'taiKhoanId' => $userLogin->id,
                'hoTen' => $userLogin->username,
                'loaiNguoiDung' => 0,
            ]);
        }

        // Tìm đoạn chat đang hoạt động
        $conversation = CuocTroChuyen::where('khachHangId', $khachHang->id)
                                     ->where('trangThaiChat', 'DANG_HOAT_DONG')
                                     ->first();

        if (!$conversation) {
            // Tạo đoạn chat mới
            $conversation = CuocTroChuyen::create([
                'khachHangId' => $khachHang->id,
                'trangThaiChat' => 'DANG_HOAT_DONG'
            ]);
        }

        return response()->json($conversation);
    }

    /**
     * Lấy lịch sử tin nhắn
     */
    public function getMessages($conversationId)
    {
        $userLogin = Auth::guard('api')->user();

        // Tự động đánh dấu tin nhắn đối phương là ĐÃ XEM (daDoc = 1)
        if ($userLogin) {
            if ($userLogin->MAROLE == 3) {
                // Khách hàng xem tin nhắn của nhân viên (1) hoặc bot AI (2)
                TinNhan::where('cuocTroChuyenId', $conversationId)
                       ->whereIn('nguoiGuiType', [1, 2])
                       ->where('daDoc', 0)
                       ->update(['daDoc' => 1]);
            } else {
                // Nhân viên xem tin nhắn của khách hàng (0)
                TinNhan::where('cuocTroChuyenId', $conversationId)
                       ->where('nguoiGuiType', 0)
                       ->where('daDoc', 0)
                       ->update(['daDoc' => 1]);
            }
        }

        $messages = TinNhan::where('cuocTroChuyenId', $conversationId)
                           ->orderBy('id', 'asc')
                           ->get();
        return response()->json($messages);
    }

    /**
     * Gửi tin nhắn mới
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'NOIDUNG' => 'required|string'
        ]);

        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $conversation = CuocTroChuyen::find($conversationId);
        if (!$conversation) {
            return response()->json(['error' => 'Đoạn chat không tồn tại'], 404);
        }

        $loaiNguoiGui = ($userLogin->MAROLE == 3) ? 'khachhang' : 'nhanvien';

        // Nếu là nhân viên gửi tin -> Cập nhật trạng thái trực tuyến
        if ($loaiNguoiGui === 'nhanvien') {
            \App\Services\StaffPresenceService::recordOnline((int)$userLogin->id);
        }

        $tinNhan = TinNhan::create([
            'cuocTroChuyenId' => $conversationId,
            'nguoiGuiType' => $loaiNguoiGui,
            'noiDung' => $request->NOIDUNG,
        ]);

        // Cập nhật thời gian update của đoạn chat
        $conversation->touch();

        // Broadcast sự kiện qua Pusher (an toàn không gây crash nếu Pusher offline)
        try {
            broadcast(new MessageSent($tinNhan, $conversationId))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Pusher broadcast failed: ' . $e->getMessage());
        }

        // TỰ ĐỘNG PHẢN HỒI BẰNG BOT AI NẾU KHÁCH HÀNG GỬI TIN NHẮN
        if ($loaiNguoiGui === 'khachhang') {
            $geminiService = app(\App\Services\GeminiChatService::class);
            if ($geminiService->shouldReply((int) $conversationId)) {
                try {
                    $reply = $geminiService->generateReply((int) $conversationId, $request->NOIDUNG);
                    if ($reply) {
                        $botMessage = TinNhan::create([
                            'cuocTroChuyenId' => $conversationId,
                            'nguoiGuiType' => 'bot', // 2
                            'noiDung' => $reply,
                            'daDoc' => 0,
                        ]);

                        $conversation->touch();

                        try {
                            // Phát broadcast cho cả khách hàng và nhân viên
                            broadcast(new MessageSent($botMessage, $conversationId));
                        } catch (\Exception $e) {
                            Log::warning('Pusher broadcast bot reply failed: ' . $e->getMessage());
                        }

                        // Gắn bot_reply vào response để client nhận được ngay lập tức
                        $tinNhan->setAttribute('bot_reply', $botMessage);
                    }
                } catch (\Exception $e) {
                    Log::error('Bot auto-reply error: ' . $e->getMessage());
                }
            }
        }

        return response()->json($tinNhan);
    }

    /**
     * Thu hồi tin nhắn
     */
    public function recallMessage($conversationId, $messageId)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $message = TinNhan::where('cuocTroChuyenId', $conversationId)->where('id', $messageId)->first();
        if (!$message) {
            return response()->json(['error' => 'Tin nhắn không tồn tại'], 404);
        }

        $message->noiDung = '🚫 Tin nhắn đã bị thu hồi';
        $message->save();

        try {
            broadcast(new MessageSent($message, $conversationId))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Pusher broadcast failed: ' . $e->getMessage());
        }

        return response()->json($message);
    }

    /**
     * Lấy danh sách tin nhắn nội bộ
     */
    public function getInternalMessages($channel)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        // Khách hàng (MAROLE = 3) không có quyền truy cập kênh nội bộ
        if ($userLogin->MAROLE === 3) {
            return response()->json(['error' => 'Khách hàng không có quyền truy cập chat nội bộ'], 403);
        }

        $nhanVien = NhanVien::findByUsername($userLogin->username);
        $staffStore = $nhanVien?->MACUAHANG ?? $nhanVien?->cuaHangId ?? $userLogin->nguoidung?->cuaHangId ?? 1;

        // Security check
        if ($channel === 'managers' && !in_array($userLogin->MAROLE, [0, 1, 5])) {
            return response()->json(['error' => 'Không có quyền truy cập nhóm quản lý'], 403);
        }

        if (str_starts_with($channel, 'branch_')) {
            $branchId = str_replace('branch_', '', $channel);
            if ($staffStore != $branchId && !in_array($userLogin->MAROLE, [0, 5])) {
                return response()->json(['error' => 'Không có quyền truy cập kênh này'], 403);
            }
        }

        // Đánh dấu tin nhắn của người khác trong kênh là đã đọc
        DB::table('internal_messages')
            ->where('channel_name', $channel)
            ->where('sender_username', '!=', $userLogin->username)
            ->where('daDoc', 0)
            ->update(['daDoc' => 1]);

        $messages = DB::table('internal_messages')
            ->leftJoin('taikhoan', 'internal_messages.sender_username', '=', 'taikhoan.username')
            ->leftJoin('nguoidung', 'taikhoan.id', '=', 'nguoidung.taiKhoanId')
            ->where('internal_messages.channel_name', $channel)
            ->select(
                'internal_messages.*',
                DB::raw("COALESCE(nguoidung.hoTen, internal_messages.sender_username, 'Nhân viên') as HOTEN")
            )
            ->orderBy('internal_messages.id', 'asc')
            ->get();

        return response()->json($messages);
    }

    /**
     * Gửi tin nhắn nội bộ
     */
    public function sendInternalMessage(Request $request, $channel)
    {
        $request->validate(['NOIDUNG' => 'required|string']);
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        // Khách hàng không có quyền gửi tin nội bộ
        if ($userLogin->MAROLE === 3) {
            return response()->json(['error' => 'Khách hàng không có quyền truy cập chat nội bộ'], 403);
        }

        $nhanVien = NhanVien::findByUsername($userLogin->username);
        $staffStore = $nhanVien?->MACUAHANG ?? $nhanVien?->cuaHangId ?? $userLogin->nguoidung?->cuaHangId ?? 1;

        // Security check
        if ($channel === 'managers' && !in_array($userLogin->MAROLE, [0, 1, 5])) {
            return response()->json(['error' => 'Không có quyền truy cập nhóm quản lý'], 403);
        }

        if (str_starts_with($channel, 'branch_')) {
            $branchId = str_replace('branch_', '', $channel);
            if ($staffStore != $branchId && !in_array($userLogin->MAROLE, [0, 5])) {
                return response()->json(['error' => 'Không có quyền truy cập kênh này'], 403);
            }
        }

        $now = now();
        $id = DB::table('internal_messages')->insertGetId([
            'channel_name' => $channel,
            'sender_username' => $userLogin->username,
            'message' => $request->NOIDUNG,
            'daDoc' => 0,
            'createdAt' => $now,
            'updatedAt' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $msg = DB::table('internal_messages')
            ->leftJoin('taikhoan', 'internal_messages.sender_username', '=', 'taikhoan.username')
            ->leftJoin('nguoidung', 'taikhoan.id', '=', 'nguoidung.taiKhoanId')
            ->where('internal_messages.id', $id)
            ->select(
                'internal_messages.*',
                DB::raw("COALESCE(nguoidung.hoTen, internal_messages.sender_username, 'Nhân viên') as HOTEN")
            )
            ->first();

        try {
            broadcast(new InternalMessageSent($msg, $channel))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Internal Pusher broadcast failed: ' . $e->getMessage());
        }

        return response()->json($msg);
    }

    /**
     * Thu hồi tin nhắn nội bộ
     */
    public function recallInternalMessage($channel, $msgId)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $message = DB::table('internal_messages')
            ->where('channel_name', $channel)
            ->where('id', $msgId)
            ->first();

        if (!$message) {
            return response()->json(['error' => 'Tin nhắn không tồn tại'], 404);
        }

        // Chỉ người gửi hoặc Admin (role 0) được thu hồi
        if ($message->sender_username !== $userLogin->username && $userLogin->MAROLE !== 0) {
            return response()->json(['error' => 'Bạn không có quyền thu hồi tin nhắn này'], 403);
        }

        $now = now();
        DB::table('internal_messages')
            ->where('id', $msgId)
            ->update([
                'message' => '🚫 Tin nhắn đã bị thu hồi',
                'updated_at' => $now,
                'updatedAt' => $now,
            ]);

        $updatedMsg = DB::table('internal_messages')
            ->leftJoin('taikhoan', 'internal_messages.sender_username', '=', 'taikhoan.username')
            ->leftJoin('nguoidung', 'taikhoan.id', '=', 'nguoidung.taiKhoanId')
            ->where('internal_messages.id', $msgId)
            ->select(
                'internal_messages.*',
                DB::raw("COALESCE(nguoidung.hoTen, internal_messages.sender_username, 'Nhân viên') as HOTEN")
            )
            ->first();

        try {
            broadcast(new InternalMessageSent($updatedMsg, $channel))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Internal Pusher broadcast failed: ' . $e->getMessage());
        }

        return response()->json($updatedMsg);
    }

    /**
     * Lấy trạng thái trực tuyến của nhân viên CSKH
     */
    public function getStaffPresence()
    {
        return response()->json([
            'is_staff_online' => \App\Services\StaffPresenceService::isStaffOnline(),
            'online_staff_ids' => \App\Services\StaffPresenceService::getOnlineStaffIds(),
        ]);
    }

    /**
     * Ping báo nhân viên đang mở trang chat
     */
    public function pingStaffPresence()
    {
        $user = Auth::guard('api')->user();
        if ($user && $user->MAROLE != 3 && (int)$user->vaiTroId !== 6) {
            \App\Services\StaffPresenceService::recordOnline((int)$user->id);
        }

        return response()->json([
            'success' => true,
            'is_staff_online' => \App\Services\StaffPresenceService::isStaffOnline(),
        ]);
    }

    /**
     * Xóa trạng thái trực tuyến của nhân viên (phục vụ test Bot AI)
     */
    public function clearStaffPresence()
    {
        \App\Services\StaffPresenceService::clearAll();
        return response()->json([
            'is_staff_online' => false,
            'message' => 'Đã đặt trạng thái nhân viên thành Ngoại tuyến để thử nghiệm Bot AI.',
        ]);
    }
}
