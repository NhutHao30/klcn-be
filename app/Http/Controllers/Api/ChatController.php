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
     * Xác định xem người dùng hiện tại có phải là Khách hàng hay không
     */
    private function isCustomerUser($userLogin): bool
    {
        if (!$userLogin) return true;

        $vaiTroId = (int)($userLogin->vaiTroId ?? $userLogin->MAROLE ?? 0);
        $roleName = strtoupper($userLogin->role ?? $userLogin->VAITRO ?? '');

        // Nếu rõ ràng là Quản trị viên, Quản lý cửa hàng
        if ($vaiTroId === 1 || $vaiTroId === 2 || str_contains($roleName, 'ADMIN') || str_contains($roleName, 'QUANLY')) {
            return false;
        }

        // Nếu bản ghi nguoidung có loaiNguoiDung = 0 -> Khách hàng
        if (isset($userLogin->nguoidung) && $userLogin->nguoidung->loaiNguoiDung !== null) {
            return (int)$userLogin->nguoidung->loaiNguoiDung === 0;
        }

        // Mặc định vai trò 6 (Khách hàng mới), 3 (khách hàng DB cũ), hoặc 0
        return ($vaiTroId === 6 || $vaiTroId === 3 || $vaiTroId === 0 || empty($vaiTroId));
    }

    /**
     * Lấy danh sách cuộc trò chuyện
     */
    public function getConversations(Request $request)
    {
        try {
            $userLogin = Auth::guard('api')->user();
            if (!$userLogin) {
                return response()->json(['error' => 'Chưa đăng nhập'], 401);
            }

            $isCustomer = $this->isCustomerUser($userLogin);

            if ($isCustomer) {
                // Khách hàng -> Lấy danh sách đoạn chat của họ
                $khachHang = KhachHang::findByUsername($userLogin->username) ?? NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                if (!$khachHang) {
                    $khachHang = NguoiDung::create([
                        'taiKhoanId' => $userLogin->id,
                        'hoTen' => $userLogin->username ?? 'Khách hàng',
                        'loaiNguoiDung' => 0,
                    ]);
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
                try {
                    \App\Services\StaffPresenceService::recordOnline((int)$userLogin->id);
                } catch (\Throwable $eStaff) {}

                $conversations = CuocTroChuyen::with(['tinnhans' => function ($q) {
                    $q->orderBy('id', 'desc')->take(1);
                }, 'khachhang'])
                ->orderBy('updatedAt', 'desc')
                ->get();

                return response()->json($conversations);
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi getConversations: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Lỗi tải danh sách trò chuyện: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Khách hàng tạo hoặc lấy đoạn chat
     */
    public function startConversation(Request $request)
    {
        try {
            $userLogin = Auth::guard('api')->user();
            if (!$userLogin) {
                return response()->json(['error' => 'Vui lòng đăng nhập để bắt đầu trò chuyện'], 401);
            }

            $khachHang = KhachHang::findByUsername($userLogin->username) ?? NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            if (!$khachHang) {
                $khachHang = NguoiDung::create([
                    'taiKhoanId' => $userLogin->id,
                    'hoTen' => $userLogin->username ?? 'Khách hàng',
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
        } catch (\Throwable $e) {
            Log::error('Lỗi startConversation: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Lỗi khởi tạo đoạn chat: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy lịch sử tin nhắn
     */
    public function getMessages($conversationId)
    {
        $userLogin = Auth::guard('api')->user();

        // Tự động đánh dấu tin nhắn đối phương là ĐÃ XEM (daDoc = 1)
        if ($userLogin) {
            $isCustomer = $this->isCustomerUser($userLogin);
            if ($isCustomer) {
                // Khách hàng xem tin nhắn của nhân viên (1) hoặc bot AI (2)
                TinNhan::where('cuocTroChuyenId', $conversationId)
                       ->whereIn('nguoiGuiType', [1, 2, 'nhanvien', 'bot'])
                       ->where('daDoc', 0)
                       ->update(['daDoc' => 1]);
            } else {
                // Nhân viên xem tin nhắn của khách hàng (0)
                TinNhan::where('cuocTroChuyenId', $conversationId)
                       ->whereIn('nguoiGuiType', [0, 'khachhang'])
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

        $isCustomer = $this->isCustomerUser($userLogin);
        $loaiNguoiGui = $isCustomer ? 'khachhang' : 'nhanvien';

        // Nếu là nhân viên gửi tin -> Cập nhật trạng thái trực tuyến
        if (!$isCustomer) {
            try {
                \App\Services\StaffPresenceService::recordOnline((int)$userLogin->id);
            } catch (\Throwable $te) {}
        }

        $tinNhan = TinNhan::create([
            'cuocTroChuyenId' => $conversationId,
            'nguoiGuiType' => $loaiNguoiGui,
            'noiDung' => $request->NOIDUNG,
        ]);

        // Cập nhật thời gian update của đoạn chat
        $conversation->touch();

        // Broadcast sự kiện qua Pusher (an toàn)
        try {
            broadcast(new MessageSent($tinNhan, $conversationId))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Pusher broadcast failed: ' . $e->getMessage());
        }

        // TỰ ĐỘNG PHẢN HỒI BẰNG BOT AI NẾU KHÁCH HÀNG GỬI TIN NHẮN
        if ($isCustomer) {
            try {
                $geminiService = app(\App\Services\GeminiChatService::class);
                if ($geminiService->shouldReply((int) $conversationId)) {
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
                            broadcast(new MessageSent($botMessage, $conversationId));
                        } catch (\Exception $e) {
                            Log::warning('Pusher broadcast bot reply failed: ' . $e->getMessage());
                        }

                        // Gắn bot_reply vào response để client (web/mobile) nhận được ngay lập tức
                        $tinNhan->setAttribute('bot_reply', $botMessage);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Bot auto-reply error: ' . $e->getMessage());
            }
        }

        return response()->json($tinNhan);
    }

    /**
     * Thu hồi tin nhắn
     */
    public function recallMessage(Request $request, $messageId)
    {
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $tinNhan = TinNhan::find($messageId);
        if (!$tinNhan) {
            return response()->json(['error' => 'Tin nhắn không tồn tại'], 404);
        }

        $tinNhan->daThuHoi = 1;
        $tinNhan->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã thu hồi tin nhắn',
            'data' => $tinNhan
        ]);
    }

    /**
     * Lấy tin nhắn nội bộ
     */
    public function getInternalMessages($channel)
    {
        $messages = TinNhan::where('channel', $channel)
                           ->orderBy('id', 'asc')
                           ->get();
        return response()->json($messages);
    }

    /**
     * Gửi tin nhắn nội bộ
     */
    public function sendInternalMessage(Request $request, $channel)
    {
        $request->validate([
            'NOIDUNG' => 'required|string'
        ]);

        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $tinNhan = TinNhan::create([
            'channel' => $channel,
            'nguoiGuiType' => 'nhanvien',
            'noiDung' => $request->NOIDUNG,
            'taiKhoanId' => $userLogin->id,
        ]);

        try {
            broadcast(new InternalMessageSent($tinNhan, $channel))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Pusher internal broadcast failed: ' . $e->getMessage());
        }

        return response()->json($tinNhan);
    }
}
