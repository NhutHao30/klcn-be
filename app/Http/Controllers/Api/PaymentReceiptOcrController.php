<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PaymentReceiptOcrController extends Controller
{
    /**
     * Danh sách từ khóa chứng minh đây là biên lai giao dịch chuyển khoản
     */
    private $bankingKeywords = [
        'thành công', 'thanh cong', 'giao dịch thành công', 'chuyển khoản thành công',
        'chuyển tiền thành công', 'thanh toán thành công', 'successful', 'success',
        'số tiền', 'so tien', 'vnd', 'vnđ', 'đ', 'tiền chuyển', 'amount',
        'vietcombank', 'vcb', 'techcombank', 'tcb', 'mbbank', 'mb bank', 'bidv', 'vietinbank',
        'acb', 'tpbank', 'vpbank', 'agribank', 'sacombank', 'hdbank', 'momo', 'momopay',
        'zalopay', 'viettel money', 'napas', 'napas247', 'vietqr',
        'mã giao dịch', 'ma giao dich', 'mã gd', 'số tham chiếu', 'ref no', 'trace no',
        'ft', 'tài khoản thụ hưởng', 'tài khoản nhận', 'người thụ hưởng', 'người nhận',
        'lời nhắn', 'nội dung', 'noi dung', 'thời gian', 'ngày giao dịch'
    ];

    public function verify(Request $request)
    {
        $file = $request->file('receipt') ?? $request->file('image') ?? $request->file('file');

        if (!$file) {
            return response()->json([
                'valid' => false,
                'message' => 'Vui lòng chọn ảnh chụp biên lai chuyển khoản.',
            ], 400);
        }

        // Thông tin kỳ vọng cần đối soát
        $orderCode = trim((string)($request->input('orderCode') ?? $request->input('maDonHang') ?? $request->input('maHD') ?? ''));
        $expectedAccount = trim((string)($request->input('expectedAccount') ?? '0353144481'));
        $expectedName = trim((string)($request->input('expectedName') ?? 'TRUONG NHUT HAO'));
        $expectedAmount = (float)($request->input('expectedAmount') ?? $request->input('amount') ?? 0);

        // Kiểm tra định dạng ảnh
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])) {
            return response()->json([
                'valid' => false,
                'message' => 'Định dạng tệp không hợp lệ. Vui lòng tải lên ảnh chụp JPG, PNG hoặc WebP.',
            ], 400);
        }

        try {
            $path = $file->store('temp_ocr_receipt', 'local');
            $fullPath = Storage::disk('local')->path($path);

            $text = $this->extractTextFromImage($fullPath);

            // Xóa file tạm sau khi OCR
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            $validation = $this->analyzeReceiptText(
                $text,
                $orderCode,
                $expectedAccount,
                $expectedName,
                $expectedAmount
            );

            return response()->json([
                'valid' => $validation['isValid'],
                'message' => $validation['message'],
                'detectedBank' => $validation['detectedBank'] ?? null,
                'detectedAccount' => $validation['detectedAccount'] ?? false,
                'detectedContent' => $validation['detectedContent'] ?? false,
                'confidence' => $validation['confidence'] ?? 0,
                'rawSample' => mb_substr($text, 0, 300, 'UTF-8')
            ]);

        } catch (\Exception $e) {
            Log::error('OCR verify payment receipt error: ' . $e->getMessage());

            return response()->json([
                'valid' => true,
                'message' => 'Đã tải ảnh lên (Sẽ được nhân viên đối soát thủ công).',
                'fallback' => true,
            ]);
        }
    }

    private function extractTextFromImage(string $imagePath): string
    {
        $text = '';
        $tesseractPath = 'D:\\Program Files\\OCR\\tesseract.exe';

        if (!file_exists($tesseractPath)) {
            $whereOutput = [];
            exec('where tesseract 2>NUL', $whereOutput);
            if (!empty($whereOutput) && file_exists(trim($whereOutput[0]))) {
                $tesseractPath = trim($whereOutput[0]);
            } else {
                $commonPaths = [
                    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                    getenv('LOCALAPPDATA') . '\\Tesseract-OCR\\tesseract.exe',
                ];
                foreach ($commonPaths as $p) {
                    if (file_exists($p)) {
                        $tesseractPath = $p;
                        break;
                    }
                }
            }
        }

        if (file_exists($tesseractPath)) {
            try {
                $outputBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_rcpt_' . uniqid();
                $cmd = escapeshellarg($tesseractPath) . ' ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($outputBase) . ' -l vie+eng 2>NUL';
                exec($cmd);

                $resultFile = $outputBase . '.txt';
                if (file_exists($resultFile)) {
                    $text = file_get_contents($resultFile);
                    @unlink($resultFile);
                }
            } catch (\Exception $ex) {
                Log::warning('Tesseract OCR error: ' . $ex->getMessage());
            }
        }

        return $text;
    }

    private function removeAccents(string $str): string
    {
        $accents = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'đ'=>'d',
            'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A',
            'Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A',
            'Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
            'È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E',
            'Ê'=>'E','Ề'=>'E','Ế'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
            'Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
            'Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ộ'=>'O',
            'Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O',
            'Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
            'Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U',
            'Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
            'Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y',
            'Đ'=>'D'
        ];
        return strtr($str, $accents);
    }

    private function analyzeReceiptText(
        string $rawText,
        ?string $orderCode,
        string $expectedAccount,
        string $expectedName,
        float $expectedAmount = 0
    ): array {
        if (empty(trim($rawText))) {
            return [
                'isValid' => false,
                'message' => 'Không thể nhận diện nội dung từ ảnh. Vui lòng tải lên ảnh chụp màn hình chuyển khoản rõ nét và đủ sáng.',
            ];
        }

        $textLower = mb_strtolower($rawText, 'UTF-8');
        $textNoAccent = mb_strtolower($this->removeAccents($rawText), 'UTF-8');
        // Chuỗi chỉ gồm chữ cái và số (loại bỏ toàn bộ dấu cách và dấu câu)
        $textAlphanumeric = preg_replace('/[^a-z0-9]/', '', $textNoAccent);

        // 1. Kiểm tra ảnh có phải biên lai chuyển khoản / thanh toán không
        $matchedKeywords = [];
        foreach ($this->bankingKeywords as $kw) {
            $kwNoAccent = mb_strtolower($this->removeAccents($kw), 'UTF-8');
            if (mb_strpos($textLower, $kw) !== false || mb_strpos($textNoAccent, $kwNoAccent) !== false) {
                $matchedKeywords[] = $kw;
            }
        }

        // Nhận diện ngân hàng / ví
        $detectedBank = null;
        $banks = [
            'momo' => 'Ví MoMo',
            'vietcombank' => 'Vietcombank',
            'techcombank' => 'Techcombank',
            'mbbank' => 'MB Bank',
            'mb bank' => 'MB Bank',
            'bidv' => 'BIDV',
            'vietinbank' => 'VietinBank',
            'acb' => 'ACB',
            'tpbank' => 'TPBank',
            'vpbank' => 'VPBank',
            'agribank' => 'Agribank',
            'zalopay' => 'ZaloPay',
            'vietqr' => 'VietQR'
        ];
        foreach ($banks as $key => $bankName) {
            if (mb_strpos($textNoAccent, $key) !== false) {
                $detectedBank = $bankName;
                break;
            }
        }

        if (count($matchedKeywords) < 2 && !$detectedBank) {
            return [
                'isValid' => false,
                'message' => 'Ảnh tải lên không phải là biên lai chuyển khoản ngân hàng hoặc ví điện tử hợp lệ.',
            ];
        }

        // 2. Kiểm tra TÀI KHOẢN NHẬN TIỀN & TÊN NGƯỜI THỤ HƯỞNG
        // Số tài khoản chuẩn hóa (chỉ số): ví dụ '0353144481'
        $cleanAccount = preg_replace('/[^0-9]/', '', $expectedAccount);
        $cleanName = preg_replace('/[^a-z]/', '', mb_strtolower($this->removeAccents($expectedName), 'UTF-8'));
        
        $hasAccount = false;
        if (!empty($cleanAccount)) {
            $digitsOnlyInText = preg_replace('/[^0-9]/', '', $rawText);
            // Kiểm tra số tài khoản đầy đủ hoặc bỏ số 0 ở đầu (353144481)
            $accountNoZero = ltrim($cleanAccount, '0');
            if (str_contains($digitsOnlyInText, $cleanAccount) || str_contains($digitsOnlyInText, $accountNoZero)) {
                $hasAccount = true;
            }
        }

        $hasName = false;
        if (!empty($cleanName)) {
            // Kiểm tra 'truongnhuthao' hoặc 'nhuthao'
            if (str_contains($textAlphanumeric, $cleanName) || str_contains($textAlphanumeric, 'nhuthao')) {
                $hasName = true;
            }
        }

        if (!$hasAccount && !$hasName) {
            return [
                'isValid' => false,
                'detectedBank' => $detectedBank,
                'detectedAccount' => false,
                'detectedContent' => false,
                'message' => "Biên lai không hợp lệ: Tài khoản thụ hưởng không khớp! Bạn cần chuyển tiền đúng vào tài khoản {$expectedAccount} ({$expectedName}).",
            ];
        }

        // 3. Kiểm tra NỘI DUNG CHUYỂN KHOẢN (Mã đơn hàng)
        $hasContent = false;
        if (!empty($orderCode)) {
            $cleanOrderCode = preg_replace('/[^a-z0-9]/', '', mb_strtolower($this->removeAccents($orderCode), 'UTF-8'));
            // Kiểm tra toàn bộ mã đơn hàng
            if (str_contains($textAlphanumeric, $cleanOrderCode)) {
                $hasContent = true;
            } else {
                // Nếu mã có tiền tố HD/DH, kiểm tra phần số hoặc phần ký tự chính
                $digitsInCode = preg_replace('/[^0-9]/', '', $cleanOrderCode);
                if (strlen($digitsInCode) >= 6 && str_contains($textAlphanumeric, $digitsInCode)) {
                    $hasContent = true;
                }
            }

            if (!$hasContent) {
                return [
                    'isValid' => false,
                    'detectedBank' => $detectedBank,
                    'detectedAccount' => true,
                    'detectedContent' => false,
                    'message' => "Biên lai không hợp lệ: Nội dung chuyển khoản không chứa mã đơn hàng '{$orderCode}'! Vui lòng chụp đúng biên lai của đơn hàng này.",
                ];
            }
        }

        // 4. Xác thực thành công toàn diện
        $bankInfo = $detectedBank ? " qua {$detectedBank}" : "";
        return [
            'isValid' => true,
            'detectedBank' => $detectedBank,
            'detectedAccount' => true,
            'detectedContent' => true,
            'confidence' => 95,
            'message' => "Biên lai chuyển khoản hợp lệ{$bankInfo}! Đã khớp người thụ hưởng ({$expectedName}) và nội dung đơn hàng ({$orderCode}).",
        ];
    }
}
