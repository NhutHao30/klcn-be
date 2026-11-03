<?php

namespace App\Services;

use App\Models\CuaHang;
use App\Models\SanPham;
use App\Models\TinNhan;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    protected string $apiKey;
    protected string $model;
    protected string $mode;
    protected bool $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-3.8-flash'));
        $this->mode = config('services.gemini.mode', env('CHATBOT_MODE', 'always'));
        $this->enabled = (bool) config('services.gemini.auto_reply', env('CHATBOT_AUTO_REPLY', true));
    }

    /**
     * Kiểm tra xem Bot có nên tự động trả lời không
     */
    public function shouldReply(int $conversationId): bool
    {
        if (!$this->enabled || empty($this->apiKey)) {
            return false;
        }

        // Nếu có nhân viên/CSKH đang trực tuyến trên web -> Bot nhường cho nhân viên hỗ trợ trực tiếp
        if ($this->mode !== 'force_always' && \App\Services\StaffPresenceService::isStaffOnline()) {
            return false;
        }

        if ($this->mode === 'night_only') {
            $hour = (int) now()->timezone('Asia/Ho_Chi_Minh')->format('H');
            // Ngoài giờ làm việc: trước 8h sáng hoặc từ 21h tối trở đi
            return ($hour >= 21 || $hour < 8);
        }

        return true;
    }

    /**
     * Tạo câu trả lời từ Gemini API với dữ liệu thời gian thực
     */
    public function generateReply(int $conversationId, string $customerMessage): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning('GeminiChatService: GEMINI_API_KEY is not configured.');
            return null;
        }

        // 0. CHẶN NGAY CÁC CÂU HỎI VỀ DỮ LIỆU NỘI BỘ, TÀI CHÍNH, DOANH THU (Fast-Security Interceptor)
        $lowerMsg = mb_strtolower(trim($customerMessage));
        if (
            str_contains($lowerMsg, 'doanh thu') || 
            str_contains($lowerMsg, 'doanh so') || 
            str_contains($lowerMsg, 'doanh số') || 
            str_contains($lowerMsg, 'lợi nhuận') || 
            str_contains($lowerMsg, 'loi nhuan') || 
            str_contains($lowerMsg, 'tiền thu được') || 
            str_contains($lowerMsg, 'bán được bao nhiêu tiền') ||
            str_contains($lowerMsg, 'lãi bao nhiêu') ||
            str_contains($lowerMsg, 'lai bao nhieu') ||
            str_contains($lowerMsg, 'lương nhân viên') ||
            str_contains($lowerMsg, 'luong nhan vien') ||
            str_contains($lowerMsg, 'chi phí') ||
            str_contains($lowerMsg, 'chi phi')
        ) {
            return "Dạ xin lỗi bạn, đây là thông tin kinh doanh nội bộ của công ty nên em không được phép chia sẻ ạ. Em chỉ có thể hỗ trợ tư vấn các thông tin về thực đơn bánh, giá bán, khuyến mãi và địa chỉ chi nhánh thôi bạn nhé! 😊";
        }

        // 1. Dữ liệu cửa hàng thực tế từ database
        $storesContext = $this->buildStoresContext();

        // 2. Dữ liệu sản phẩm thực tế từ database
        $productsContext = $this->buildProductsContext();

        // 3. Lấy 6 tin nhắn gần nhất để giữ ngữ cảnh hội thoại
        $history = TinNhan::where('cuocTroChuyenId', $conversationId)
            ->orderBy('id', 'desc')
            ->take(6)
            ->get()
            ->reverse();

        $conversationText = "";
        foreach ($history as $msg) {
            $sender = ($msg->nguoiGuiType == 0) ? 'Khách hàng' : (($msg->nguoiGuiType == 2) ? 'Trợ lý Givral AI' : 'Nhân viên CSKH');
            $conversationText .= "{$sender}: {$msg->noiDung}\n";
        }

        $systemInstruction = <<<TEXT
Bạn là "Trợ lý ảo Givral AI" - trợ lý tư vấn khách hàng của chuỗi cửa hàng bánh ngọt Givral Bakery.
Giờ mở cửa phục vụ: 07:00 - 22:00 hàng ngày.

QUY TẮC BẢO MẬT & PHẠM VI TRẢ LỜI BẮT BUỘC (TUÂN THỦ 100%):
1. PHẠM VI HỖ TRỢ:
   - Bạn CHỈ ĐƯỢC PHÉP trả lời các câu hỏi dịch vụ đơn giản dành cho khách hàng:
     + Danh mục thực đơn bánh, tên các loại bánh, mô tả hương vị, giá bán niêm yết.
     + Địa chỉ, hotline, giờ mở cửa các chi nhánh của Givral Bakery.
     + Gợi ý bánh theo dịp (sinh nhật, thôi nôi, kỷ niệm, tặng người yêu, sự kiện...).
     + Cách bảo quản bánh và thông tin khuyến mãi công khai.
   - Trả lời ngắn gọn, lịch sự, ấm áp (khoảng 2-4 câu).

2. BẢO MẬT TUYỆT ĐỐI - KHÔNG TIẾT LỘ DOANH THU & TÀI CHÍNH NỘI BỘ:
   - BẠN TUYỆT ĐỐI KHÔNG ĐƯỢC trả lời, ước tính hay chia sẻ bất kỳ thông tin nào về DOANH THU, DOANH SỐ, LỢI NHUẬN, CHI PHÍ, TIỀN BÁN HÀNG, LƯƠNG NHÂN VIÊN của cửa hàng hay chuỗi chi nhánh. Đây là DỮ LIỆU TUYỆT MẬT NỘI BỘ.
   - Nếu khách hàng hỏi về doanh thu hoặc tài chính:
     => BẮT BUỘC TỪ CHỐI LỊCH SỰ:
     "Dạ xin lỗi bạn, đây là thông tin kinh doanh nội bộ của công ty nên em không thể chia sẻ được ạ. Em chỉ có thể hỗ trợ bạn các thông tin về thực đơn bánh, giá bán, khuyến mãi và địa chỉ chi nhánh thôi bạn nhé!"

3. BẢO MẬT TỒN KHO - KHÔNG NÊU CON SỐ TỒN KHO CỤ THỂ:
   - Khi khách hỏi còn bao nhiêu cái (ví dụ: "bánh su kem còn bao nhiêu cái", "tồn kho còn mấy cái"):
     + CHỈ ĐƯỢC trả lời trạng thái chung là "Sản phẩm hiện đang Còn hàng" hoặc "Sản phẩm hiện đang Tạm hết hàng".
     + TUYỆT ĐỐI KHÔNG nói con số tồn kho cụ thể (không được nói còn 10 cái, 50 cái...).
     + Nếu khách cần đặt số lượng lớn cho sự kiện, hướng dẫn khách liên hệ trực tiếp hotline chi nhánh để được tiệm làm bánh tươi mới và chuẩn bị chu đáo nhất.

4. CÂU HỎI NGOÀI LỀ & KHIẾU NẠI:
   - Không trả lời các câu hỏi không liên quan đến tiệm bánh Givral (chính trị, giải toán, code, đời tư...).
   - Nếu khách có khiếu nại, yêu cầu đổi trả, hoàn tiền: "Dạ nhân viên CSKH của Givral Bakery sẽ liên hệ trực tiếp để hỗ trợ bạn chu đáo nhất ạ!"

--- DANH SÁCH CHI NHÁNH ĐANG HOẠT ĐỘNG ---
{$storesContext}

--- DANH MỤC & SẢN PHẨM HIỆN CÓ TẠI CỬA HÀNG ---
{$productsContext}
TEXT;

        $prompt = <<<TEXT
LỊCH SỬ TIN NHẮN GẦN ĐÂY:
{$conversationText}
Khách hàng vừa hỏi: "{$customerMessage}"

Hãy đóng vai Trợ lý ảo Givral AI và phản hồi ngắn gọn, tự nhiên, tuân thủ đúng quy tắc bảo mật trên:
TEXT;

        // Danh sách model ưu tiên (tự động chuyển sang model dự phòng nếu model chính bị 503 quá tải)
        $modelsToTry = array_values(array_unique([
            $this->model,
            'gemini-3.5-flash',
            'gemini-3.8-flash',
            'gemini-3-flash-preview',
        ]));

        $client = new Client([
            'timeout' => 12.0,
            'verify' => false, // Bỏ qua SSL cURL local trên Windows dev
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Tăng tốc phân giải DNS IPv4 trên Windows
            ],
        ]);

        foreach ($modelsToTry as $targetModel) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$this->apiKey}";

                $response = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'systemInstruction' => [
                            'parts' => [
                                ['text' => $systemInstruction]
                            ]
                        ],
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 2048,
                        ]
                    ]
                ]);

                $result = json_decode($response->getBody()->getContents(), true);
                $botReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if ($botReply) {
                    return trim($botReply);
                }
            } catch (\Exception $e) {
                Log::warning("GeminiChatService model {$targetModel} failed: " . $e->getMessage() . ". Trying next fallback if available.");
            }
        }

        return null;
    }

    /**
     * Tạo chuỗi thông tin các cửa hàng đang hoạt động
     */
    protected function buildStoresContext(): string
    {
        $stores = CuaHang::where('trangThai', 1)->get();
        if ($stores->isEmpty()) {
            return "Hiện tại hệ thống đang cập nhật danh sách chi nhánh.";
        }

        $lines = [];
        foreach ($stores as $store) {
            $lines[] = "- {$store->tenCuaHang}: {$store->diaChiChiTiet} (SĐT: {$store->soDienThoai})";
        }
        return implode("\n", $lines);
    }

    /**
     * Tạo chuỗi thông tin sản phẩm và giá bán thời gian thực
     */
    protected function buildProductsContext(): string
    {
        $products = SanPham::with('loaiSanPham')
            ->where('trangThai', 1)
            ->get();

        if ($products->isEmpty()) {
            return "Hiện tại hệ thống đang cập nhật danh sách sản phẩm.";
        }

        $lines = [];
        foreach ($products as $sp) {
            $loai = $sp->loaiSanPham?->tenLoai ?? 'Bánh ngọt';
            $gia = number_format($sp->giaBan) . 'đ';
            $ton = $sp->soLuongTon;
            $statusTon = $ton > 0 ? "Còn hàng ({$ton} cái)" : "Tạm hết";
            $moTa = $sp->moTa ? " - " . mb_substr(strip_tags($sp->moTa), 0, 80) : "";
            $lines[] = "- [{$loai}] {$sp->tenSanPham}: Giá {$gia} ({$statusTon}){$moTa}";
        }
        return implode("\n", $lines);
    }
}
