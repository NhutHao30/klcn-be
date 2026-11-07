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
     * Lấy hồ sơ người dùng (nguoidung) tương ứng
     */
    private function resolveProfile($userLogin)
    {
        if (!$userLogin) return null;
        try {
            $profile = NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            if (!$profile) {
                $profile = NguoiDung::where('taikhoan_id', $userLogin->id)->first();
            }
            if (!$profile) {
                $profile = KhachHang::findByUsername($userLogin->username);
            }
            if (!$profile) {
                $profile = new NguoiDung();
                $tbl = (new NguoiDung)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'taiKhoanId')) {
                    $profile->taiKhoanId = $userLogin->id;
                } else {
                    $profile->taikhoan_id = $userLogin->id;
                }
                $profile->hoTen = $userLogin->username ?? 'Khách hàng';
                $profile->loaiNguoiDung = 0;
                $profile->save();
            }
            return $profile;
        } catch (\Throwable $e) {
            Log::warning('resolveProfile fallback: ' . $e->getMessage());
            return (object)['id' => (int)$userLogin->id, 'hoTen' => $userLogin->username ?? 'Khách hàng'];
        }
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
                $khachHang = $this->resolveProfile($userLogin);
                $khId = $khachHang ? $khachHang->id : $userLogin->id;
                $khCol = CuocTroChuyen::getKhachHangCol();

                $conversations = CuocTroChuyen::with(['tinnhans' => function ($q) {
                    $q->orderBy('id', 'desc')->take(1);
                }, 'khachhang'])
                ->where($khCol, $khId)
                ->orderBy('id', 'desc')
                ->get();

                return response()->json($conversations);

            } else {
                // Nhân viên / Admin / CSKH -> Ghi nhận đang trực tuyến & Lấy tất cả đoạn chat
                try {
                    \App\Services\StaffPresenceService::recordOnline((int)$userLogin->id);
                } catch (\Throwable $eStaff) {}

                $updatedCol = (new CuocTroChuyen)->getUpdatedAtColumn();

                $conversations = CuocTroChuyen::with(['tinnhans' => function ($q) {
                    $q->orderBy('id', 'desc')->take(1);
                }, 'khachhang'])
                ->orderBy($updatedCol, 'desc')
                ->get();

                return response()->json($conversations);
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi getConversations: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Lỗi tải danh sách trò chuyện: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Khách hàng tạo hoặc lấy đoạn chat DUY NHẤT của mình
     */
    public function startConversation(Request $request)
    {
        try {
            $userLogin = Auth::guard('api')->user();
            if (!$userLogin) {
                return response()->json(['error' => 'Vui lòng đăng nhập để bắt đầu trò chuyện'], 401);
            }

            $khachHang = $this->resolveProfile($userLogin);
            $khId = $khachHang ? $khachHang->id : $userLogin->id;
            $khCol = CuocTroChuyen::getKhachHangCol();
            $ttCol = CuocTroChuyen::getTrangThaiCol();

            // Tìm đoạn chat gần nhất của khách hàng (bảo toàn lịch sử tin nhắn)
            $conversation = CuocTroChuyen::where($khCol, $khId)->orderBy('id', 'desc')->first();

            if (!$conversation) {
                // Tạo đoạn chat mới nếu khách hàng chưa từng chat
                $conversation = new CuocTroChuyen();
                $conversation->{$khCol} = $khId;
                $conversation->{$ttCol} = 0;
                $conversation->save();
            } else {
                // Kích hoạt lại đoạn chat nếu trước đó bị đóng
                if (($conversation->{$ttCol} ?? 0) != 0) {
                    $conversation->{$ttCol} = 0;
                    $conversation->save();
                }
            }

            return response()->json($conversation);
        } catch (\Throwable $e) {
            Log::error('Lỗi startConversation: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'id' => 1,
                'MACUOCTROCHUYEN' => 1,
                'khachHangId' => Auth::guard('api')->id() ?? 1,
                'trangThaiChat' => 0,
                'TRANGTHAI' => 'DANG_HOAT_DONG',
                'created_at' => now()->toIso8601String()
            ]);
        }
    }

    /**
     * Lấy lịch sử tin nhắn
     */
    public function getMessages($conversationId)
    {
        $userLogin = Auth::guard('api')->user();
        $chatFk = TinNhan::getCuocTroChuyenCol();
        $typeCol = TinNhan::getNguoiGuiTypeCol();

        // Tự động đánh dấu tin nhắn đối phương là ĐÃ XEM (daDoc = 1)
        if ($userLogin) {
            $isCustomer = $this->isCustomerUser($userLogin);
            try {
                if ($isCustomer) {
                    TinNhan::where($chatFk, $conversationId)
                           ->whereIn($typeCol, [1, 2, 'nhanvien', 'bot'])
                           ->where('daDoc', 0)
                           ->update(['daDoc' => 1]);
                } else {
                    TinNhan::where($chatFk, $conversationId)
                           ->whereIn($typeCol, [0, 'khachhang'])
                           ->where('daDoc', 0)
                           ->update(['daDoc' => 1]);
                }
            } catch (\Throwable $eDoc) {}
        }

        $messages = TinNhan::where($chatFk, $conversationId)
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

        try {
            $chatFk = TinNhan::getCuocTroChuyenCol();
            $typeCol = TinNhan::getNguoiGuiTypeCol();
            $contentCol = TinNhan::getNoiDungCol();

            $tinNhan = new TinNhan();
            $tinNhan->{$chatFk} = $conversationId;
            $tinNhan->{$typeCol} = $loaiNguoiGui;
            $tinNhan->{$contentCol} = $request->NOIDUNG;

            $createdCol = $tinNhan->getCreatedAtColumn();
            $updatedCol = $tinNhan->getUpdatedAtColumn();
            $tinNhan->{$createdCol} = now();
            $tinNhan->{$updatedCol} = now();
            $tinNhan->save();

            // Cập nhật thời gian update của đoạn chat
            try {
                $conversation->touch();
            } catch (\Throwable $eTouch) {}

            // Broadcast sự kiện qua Pusher (an toàn)
            try {
                broadcast(new MessageSent($tinNhan, $conversationId))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Pusher broadcast failed: ' . $e->getMessage());
            }

            // TỰ ĐỘNG PHẢN HỒI BẰNG BOT AI NẾU KHÁCH HÀNG GỬI TIN NHẮN
            if ($isCustomer) {
                try {
                    $geminiService = app(\App\Services\GeminiChatService::class);
                    if ($geminiService->shouldReply((int) $conversationId)) {
                        $reply = $geminiService->generateReply((int) $conversationId, $request->NOIDUNG);
                        if ($reply) {
                            $botMessage = new TinNhan();
                            $botMessage->{$chatFk} = $conversationId;
                            $botMessage->{$typeCol} = 'bot'; // 2
                            $botMessage->{$contentCol} = $reply;
                            $botMessage->{$createdCol} = now();
                            $botMessage->{$updatedCol} = now();
                            if (\Illuminate\Support\Facades\Schema::hasColumn($tinNhan->getTable(), 'daDoc')) {
                                $botMessage->daDoc = 0;
                            }
                            $botMessage->save();

                            try {
                                $conversation->touch();
                            } catch (\Throwable $eTouch) {}

                            try {
                                broadcast(new MessageSent($botMessage, $conversationId));
                            } catch (\Throwable $e) {}

                            // Gắn bot_reply vào response để client (web/mobile) nhận được ngay lập tức
                            $tinNhan->setAttribute('bot_reply', $botMessage);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Bot auto-reply error: ' . $e->getMessage());
                }
            }

            return response()->json($tinNhan);
        } catch (\Throwable $e) {
            Log::error('Lỗi sendMessage: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Lỗi gửi tin nhắn: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Thu hồi tin nhắn
     */
    public function recallMessage(Request $request, $id, $msgId = null)
    {
        $targetMsgId = $msgId ?: $id;
        $userLogin = Auth::guard('api')->user();
        if (!$userLogin) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $tinNhan = TinNhan::find($targetMsgId);
        if (!$tinNhan) {
            return response()->json(['error' => 'Tin nhắn không tồn tại'], 404);
        }

        try {
            $contentCol = TinNhan::getNoiDungCol();
            $tinNhan->{$contentCol} = '🚫 Tin nhắn đã bị thu hồi';
            $tbl = $tinNhan->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($tbl, 'daThuHoi')) {
                $tinNhan->daThuHoi = 1;
            }
            $tinNhan->save();

            return response()->json([
                'success' => true,
                'message' => 'Đã thu hồi tin nhắn thành công',
                'data' => $tinNhan
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi recallMessage: ' . $e->getMessage());
            return response()->json(['error' => 'Không thể thu hồi tin nhắn: ' . $e->getMessage()], 500);
        }
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

    /**
     * Lấy trạng thái hoạt động của nhân viên CSKH
     */
    public function getStaffPresence()
    {
        return response()->json([
            'is_staff_online' => \App\Services\StaffPresenceService::isStaffOnline(),
            'online_staff_ids' => \App\Services\StaffPresenceService::getOnlineStaffIds(),
        ]);
    }

    /**
     * Nhân viên CSKH định kỳ gửi ping báo hiệu đang trực tuyến
     */
    public function pingStaffPresence()
    {
        $user = Auth::guard('api')->user();
        if ($user && in_array((int)$user->vaiTroId, [0, 1, 2, 3, 4, 5])) {
            \App\Services\StaffPresenceService::recordOnline((int)$user->id);
            return response()->json(['success' => true, 'is_staff_online' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Tài khoản không phải nhân viên CSKH']);
    }

    /**
     * Xóa toàn bộ trạng thái trực tuyến của nhân viên (hỗ trợ kiểm thử bot)
     */
    public function clearStaffPresence()
    {
        \App\Services\StaffPresenceService::clearAll();
        return response()->json(['success' => true, 'message' => 'Đã xóa toàn bộ trạng thái trực tuyến']);
    }
}
