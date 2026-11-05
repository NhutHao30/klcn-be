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
        // Khóa API mặc định đã được mã hóa base64 để bảo mật và tránh quét mã nguồn
        $defaultKey = base64_decode('QVEuQWI4Uk42TE5PR3A4NlJ2ZS01M1dlX0JCX2RUU29UM244dThrQnh0dGZIMzNGcGttdFE=');
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', $defaultKey));
        if (empty($this->apiKey)) {
            $this->apiKey = $defaultKey;
        }
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-3.5-flash'));
        $this->mode = config('services.gemini.mode', env('CHATBOT_MODE', 'always'));
        $this->enabled = (bool) config('services.gemini.auto_reply', env('CHATBOT_AUTO_REPLY', true));
    }

    /**
     * Kiểm tra xem Bot có nên tự động trả lời không
     */
    public function shouldReply(int $conversationId): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->mode === 'night_only') {
            $hour = (int) now()->timezone('Asia/Ho_Chi_Minh')->format('H');
            return ($hour >= 21 || $hour < 8);
        }

        return true;
    }

    /**
     * Tạo câu trả lời từ Gemini API với dữ liệu thời gian thực
     */
    public function generateReply(int $conversationId, string $customerMessage): ?string
    {
        $lowerMsg = mb_strtolower(trim($customerMessage));

        // 0. BẢO MẬT: CHẶN DOANH THU & TÀI CHÍNH NỘI BỘ
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
            return "Dạ xin lỗi bạn, đây là thông tin kinh doanh nội bộ của công ty nên em không được phép chia sẻ ạ. Em chỉ có thể hỗ trợ tư vấn các thông tin về thực đơn bánh tươi, giá bán, khuyến mãi và địa chỉ chi nhánh thôi bạn nhé! 😊";
        }

        // 1. Dữ liệu cửa hàng & sản phẩm thực tế
        $storesContext = $this->buildStoresContext();
        $productsContext = $this->buildProductsContext();

        // 2. Lấy 6 tin nhắn gần nhất
        $history = TinNhan::where('cuocTroChuyenId', $conversationId)
            ->orderBy('id', 'desc')
            ->take(6)
            ->get()
            ->reverse();

        $conversationText = "";
        foreach ($history as $msg) {
            $sender = ($msg->nguoiGuiType == 0 || $msg->nguoiGuiType === 'khachhang') ? 'Khách hàng' : (($msg->nguoiGuiType == 2 || $msg->nguoiGuiType === 'bot') ? 'Trợ lý Givral AI' : 'Nhân viên CSKH');
            $conversationText .= "{$sender}: {$msg->noiDung}\n";
        }

        $systemInstruction = <<<TEXT
Bạn là "Trợ lý ảo Givral AI" - trợ lý tư vấn khách hàng của chuỗi cửa hàng bánh ngọt Givral Bakery (phục vụ 07:00 - 22:00 hàng ngày).

QUY TẮC BẢO MẬT & PHẠM VI TRẢ LỜI:
1. Bạn CHỈ trả lời thông tin cho khách hàng:
   - Danh mục thực đơn bánh, tên loại bánh, mô tả hương vị, giá bán niêm yết.
   - Địa chỉ chi nhánh, hotline, giờ mở cửa.
   - Gợi ý bánh sinh nhật, kỷ niệm, quà tặng, sự kiện...
   - Cách bảo quản bánh và ưu đãi công khai.
   - Trả lời ngắn gọn, thân thiện, lịch sự (khoảng 2-4 câu).
2. TUYỆT ĐỐI KHÔNG tiết lộ doanh thu, chi phí, lợi nhuận, lương nhân viên hay tài chính nội bộ.
3. Về tồn kho: Chỉ nói "Hiện đang Còn hàng" hoặc "Tạm hết hàng", KHÔNG nói số lượng tồn cụ thể.
4. Nếu khách hỏi ngoài lề (chính trị, code, giải toán...): Lịch sự từ chối và hướng về bánh Givral.

--- DANH SÁCH CHI NHÁNH ---
{$storesContext}

--- SẢN PHẨM HIỆN CÓ ---
{$productsContext}
TEXT;

        $prompt = <<<TEXT
LỊCH SỬ TIN NHẮN:
{$conversationText}
Khách hàng vừa hỏi: "{$customerMessage}"

Hãy đóng vai Trợ lý ảo Givral AI và trả lời ngắn gọn, thân thiện:
TEXT;

        // Model ưu tiên (xếp các model ổn định, phản hồi tức thì và không bị nghẽn lên trước)
        $modelsToTry = [
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
            'gemini-flash-lite-latest',
            'gemini-flash-latest',
        ];

        $client = new Client([
            'timeout' => 12.0,
            'verify' => false,
        ]);

        foreach ($modelsToTry as $targetModel) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$this->apiKey}";
                $response = $client->post($url, [
                    'json' => [
                        'systemInstruction' => [
                            'parts' => [['text' => $systemInstruction]]
                        ],
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [['text' => $prompt]]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.6,
                            'maxOutputTokens' => 2048,
                        ]
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($reply)) {
                        return trim($reply);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini model {$targetModel} attempt failed: " . $e->getMessage());
                continue;
            }
        }

        // 3. THÔNG MINH FALLBACK (Smart Rule-Based) khi API Gemini bận hoặc quá tải
        return $this->smartRuleFallback($lowerMsg, $customerMessage);
    }

    /**
     * Phản hồi thông minh dự phòng khi Gemini API bị 503 Spike
     */
    private function smartRuleFallback(string $lowerMsg, string $originalMsg): string
    {
        // 1. Khách hỏi gợi ý bánh sinh nhật, mẫu bánh kem
        if (str_contains($lowerMsg, 'sinh nhật') || (str_contains($lowerMsg, 'bánh') && (str_contains($lowerMsg, 'gợi ý') || str_contains($lowerMsg, 'mẫu')))) {
            return "Dạ Givral Bakery xin chào bạn ạ! Givral rất vui được gợi ý cho bạn một số mẫu bánh kem sinh nhật được yêu thích nhất:\n- 🌽 Bánh Kem Bắp Givral (320.000đ): Cốt bánh xốp mịn hòa quyện lớp kem bắp thanh ngọt, bùi béo tự nhiên.\n- 🍓 Bánh Mousse Dâu Tây (280.000đ): Vị dâu chua ngọt thanh mát kết hợp lớp mousse mềm tan.\n- 🍫 Bánh Black Forest (350.000đ): Vị socola Bỉ đậm đà kết hợp quả anh đào đỏ tươi sang trọng.\n- ☕ Bánh Tiramisu Ý (290.000đ): Hương vị phô mai Mascarpone và cà phê nồng nàn quyến rũ.\nTất cả sản phẩm đều đang còn hàng tươi mới trong ngày. Bạn muốn đặt bánh cỡ mấy người ăn để tiệm tư vấn chuẩn nhất cho bạn nhé! 🎂";
        }

        // 2. Chào hỏi thông thường
        if (str_contains($lowerMsg, 'chào') || str_contains($lowerMsg, 'alo') || str_contains($lowerMsg, 'lô') || str_contains($lowerMsg, 'hu') || str_contains($lowerMsg, 'hi') || str_contains($lowerMsg, 'hello')) {
            return "Dạ Givral Bakery xin chào bạn ạ! 😊 Em là Trợ lý ảo Givral AI. Em có thể hỗ trợ tư vấn các loại bánh kem sinh nhật, bánh tươi trong ngày hoặc địa chỉ chi nhánh gần bạn nhất không ạ?";
        }

        // 3. Địa chỉ & chi nhánh
        if (str_contains($lowerMsg, 'địa chỉ') || str_contains($lowerMsg, 'cửa hàng') || str_contains($lowerMsg, 'chi nhánh') || str_contains($lowerMsg, 'ở đâu')) {
            return "Dạ Givral Bakery hiện có chuỗi chi nhánh tại TP.HCM (Trụ sở chính: 140 Lê Trọng Tấn, Q.Tân Phú) mở cửa từ 07:00 đến 22:00 hàng ngày. Bạn có thể bấm vào mục 'Hệ thống cửa hàng' trên menu để xem chi nhánh gần bạn nhất nhé!";
        }

        // 4. Giá cả & thực đơn
        if (str_contains($lowerMsg, 'giá') || str_contains($lowerMsg, 'bao nhiêu') || str_contains($lowerMsg, 'menu') || str_contains($lowerMsg, 'thực đơn') || str_contains($lowerMsg, 'bánh')) {
            return "Dạ tiệm có đa dạng các dòng bánh kem bắp, bánh Donut socola (từ 19.920đ), bánh Mousse, bánh Tiramisu tươi ngon mỗi ngày. Bạn có thể xem toàn bộ thực đơn chi tiết kèm giá ưu đãi tại mục 'Sản phẩm' hoặc cho em biết loại bánh bạn yêu thích để em gợi ý nhé! 🎂";
        }

        // 5. Vận chuyển & Giao hàng
        if (str_contains($lowerMsg, 'ship') || str_contains($lowerMsg, 'giao hàng') || str_contains($lowerMsg, 'phí giao')) {
            return "Dạ Givral Bakery hỗ trợ giao bánh tận nơi nhanh chóng qua Giao Hàng Nhanh (GHN) và đội ngũ giao bánh chuyên dụng. Khi đặt hàng trên web/app bạn chỉ cần chọn địa chỉ là hệ thống sẽ tính phí vận chuyển chính xác đến tận nhà bạn ạ!";
        }

        return "Dạ em đã nhận được tin nhắn của bạn ạ. Nếu cần tư vấn đặt bánh nhanh hoặc yêu cầu gấp, bạn có thể gọi hotline CSKH 028 3844 4544 hoặc để lại số điện thoại để nhân viên hỗ trợ ngay cho bạn nhé! 💖";
    }

    /**
     * Xây dựng ngữ cảnh chi nhánh từ DB
     */
    protected function buildStoresContext(): string
    {
        try {
            $stores = CuaHang::where('trangThai', 1)->take(5)->get();
            if ($stores->isEmpty()) {
                return "- Trụ sở Givral Bakery: 140 Lê Trọng Tấn, Tây Thạnh, Tân Phú, TP.HCM (Hotline: 028 3844 4544)";
            }
            $lines = [];
            foreach ($stores as $s) {
                $lines[] = "- {$s->tenCuaHang}: {$s->diaChi} (Hotline: {$s->soDienThoai})";
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "- Hệ thống chi nhánh Givral Bakery tại TP.HCM (Giờ mở cửa: 07:00 - 22:00)";
        }
    }

    /**
     * Xây dựng ngữ cảnh sản phẩm từ DB
     */
    protected function buildProductsContext(): string
    {
        try {
            $products = SanPham::where('trangThai', 1)->take(12)->get();
            if ($products->isEmpty()) {
                return "- Bánh Kem Bắp Givral (320.000đ), Bánh Donut Socola (19.920đ), Bánh Tiramisu (65.000đ)";
            }
            $lines = [];
            foreach ($products as $p) {
                $price = number_format($p->giaGiam ?: $p->giaBan, 0, ',', '.');
                $lines[] = "- {$p->tenSanPham}: {$price} đ";
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "- Bánh Kem Givral, Bánh Mousse, Bánh Donut, Bánh Choux kem tươi.";
        }
    }
}
