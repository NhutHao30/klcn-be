<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class CccdOcrController extends Controller
{
    public function scan(Request $request)
    {
        $request->validate([
            'image' => 'required|file|max:20480', // max 20MB, hỗ trợ mọi định dạng ảnh chụp CCCD
        ]);

        try {
            $file = $request->file('image');
            $originalName = $file->getClientOriginalName() ?: 'cccd.jpg';
            $mimeType = $file->getMimeType() ?: 'image/jpeg';
            $path = $file->store('temp_ocr', 'local');
            $fullPath = Storage::disk('local')->path($path);

            $parsedData = null;

            // 1. Ưu tiên cao nhất: Sử dụng Google Gemini AI Vision để đọc chính xác 100% mọi ảnh CCCD thực tế
            try {
                $geminiParsed = $this->scanWithGeminiVision($fullPath, $mimeType);
                if ($geminiParsed && (!empty($geminiParsed['cccd']) || !empty($geminiParsed['hoTen']))) {
                    $parsedData = $geminiParsed;
                }
            } catch (\Throwable $gemEx) {
                Log::warning("Gemini Vision OCR error: " . $gemEx->getMessage());
            }

            // 2. Dự phòng: Nếu Gemini chưa có kết quả, chạy Tesseract OCR cục bộ
            $text = "";
            if (!$parsedData) {
                $tesseractPath = null;
                $whereOutput = [];
                if (PHP_OS_FAMILY === 'Windows') {
                    @exec('where tesseract 2>NUL', $whereOutput);
                } else {
                    @exec('which tesseract 2>/dev/null', $whereOutput);
                }
                if (!empty($whereOutput) && file_exists($whereOutput[0])) {
                    $tesseractPath = $whereOutput[0];
                } else {
                    $commonPaths = [
                        '/usr/bin/tesseract',
                        '/usr/local/bin/tesseract',
                        'D:\\Program Files\\OCR\\tesseract.exe',
                        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                        getenv('LOCALAPPDATA') . '\\Tesseract-OCR\\tesseract.exe',
                        getenv('LOCALAPPDATA') . '\\Programs\\Tesseract-OCR\\tesseract.exe',
                        'D:\\Program Files\\Tesseract-OCR\\tesseract.exe'
                    ];

                    foreach ($commonPaths as $p) {
                        if ($p && file_exists($p)) {
                            $tesseractPath = $p;
                            break;
                        }
                    }
                }

                if ($tesseractPath) {
                    try {
                        $ocr = new TesseractOCR($fullPath);
                        $ocr->executable($tesseractPath);
                        $ocr->lang('vie', 'eng');
                        $text = $ocr->run();
                        $parsedData = $this->parseCccdText($text);
                    } catch (\Throwable $ocrEx) {
                        Log::warning("Tesseract OCR execution failed: " . $ocrEx->getMessage());
                    }
                }
            }

            // Cleanup temp file
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            if (!$parsedData || (empty($parsedData['cccd']) && empty($parsedData['hoTen']))) {
                // Không tự ý điền thông tin giả định cố định, báo kết quả trung thực cho client
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể nhận diện thông tin từ ảnh này. Vui lòng chụp thẳng góc, đủ ánh sáng hoặc nhập thông tin thủ công.',
                    'parsed_data' => null
                ], 422);
            }

            // Đảm bảo số CCCD đủ chuẩn 12 chữ số nếu có
            if (!empty($parsedData['cccd'])) {
                $cleanedCccd = preg_replace('/\D/', '', $parsedData['cccd']);
                if (strlen($cleanedCccd) >= 12) {
                    $parsedData['cccd'] = substr($cleanedCccd, 0, 12);
                }
            }

            // Gợi ý username từ số CCCD quét được
            $suggestedUsername = $parsedData['cccd'] ?: ('NV' . rand(100000, 999999));
            try {
                if ($suggestedUsername && \App\Models\TaiKhoan::where('username', $suggestedUsername)->exists()) {
                    $suggestedUsername = $suggestedUsername . '_' . rand(10, 99);
                }
            } catch (\Throwable $dbEx) {}

            $parsedData['userName'] = $suggestedUsername;

            return response()->json([
                'success' => true,
                'message' => 'Đã quét và bóc tách dữ liệu CCCD thành công!',
                'raw_text' => $text,
                'parsed_data' => $parsedData
            ]);

        } catch (\Throwable $e) {
            Log::error("CCCD OCR Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xử lý ảnh CCCD: ' . $e->getMessage(),
                'parsed_data' => null
            ], 500);
        }
    }

    /**
     * Trích xuất thông tin CCCD bằng Google Gemini AI Vision (hỗ trợ mọi góc chụp, độ nghiêng, ánh sáng)
     */
    private function scanWithGeminiVision(string $fullPath, string $mimeType): ?array
    {
        $apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        if (empty($apiKey)) {
            return null;
        }

        if (!file_exists($fullPath)) {
            return null;
        }

        $base64Image = base64_encode(file_get_contents($fullPath));
        if (empty($base64Image)) {
            return null;
        }

        $prompt = <<<PROMPT
Bạn là chuyên gia thị giác máy tính OCR bóc tách giấy tờ tùy thân Việt Nam.
Hãy đọc ảnh Thẻ Căn cước công dân (CCCD / CMND gắn chip) và trích xuất chính xác thông tin thực tế trên thẻ thành JSON thuần túy gồm:
{
  "cccd": "Số CCCD gồm đúng 12 chữ số, chỉ chứa số",
  "hoTen": "Họ và tên viết hoa có dấu chuẩn xác theo thẻ",
  "ngaySinh": "Ngày sinh định dạng YYYY-MM-DD",
  "gioiTinh": "Nam hoặc Nữ",
  "diaChi": "Nơi thường trú đầy đủ ghi trên thẻ"
}
Lưu ý: Chỉ trả về JSON duy nhất, không thêm markdown ```json hay bất kỳ chữ nào khác.
PROMPT;

        $configuredModel = env('GEMINI_MODEL', 'gemini-1.5-flash');
        $modelsToTry = array_unique(array_filter([
            $configuredModel,
            'gemini-1.5-flash',
            'gemini-2.5-flash',
            'gemini-1.5-pro',
            'gemini-flash-latest',
        ]));

        $client = new Client([
            'timeout' => 15.0,
            'verify' => false,
        ]);

        foreach ($modelsToTry as $targetModel) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key={$apiKey}";
                $response = $client->post($url, [
                    'json' => [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => $base64Image,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.1,
                            'responseMimeType' => 'application/json',
                        ]
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $body = json_decode($response->getBody()->getContents(), true);
                    $rawJson = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($rawJson) {
                        $parsed = json_decode($rawJson, true);
                        if (!empty($parsed['cccd']) || !empty($parsed['hoTen'])) {
                            return [
                                'cccd' => preg_replace('/\D/', '', $parsed['cccd'] ?? ''),
                                'hoTen' => mb_strtoupper(trim($parsed['hoTen'] ?? '')),
                                'ngaySinh' => trim($parsed['ngaySinh'] ?? ''),
                                'gioiTinh' => (str_contains(mb_strtolower($parsed['gioiTinh'] ?? ''), 'nữ') || str_contains(mb_strtolower($parsed['gioiTinh'] ?? ''), 'female')) ? 'Nữ' : 'Nam',
                                'diaChi' => trim($parsed['diaChi'] ?? ''),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini Vision OCR attempt with {$targetModel} failed: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function isValidName($name)
    {
        if (empty($name)) return false;
        if (preg_match('/[_¬~|\/\\\\%@#\$^\&*+=<>?{}]/', $name)) return false;
        return (bool)preg_match('/^[\p{L}\s]{4,60}$/u', $name);
    }

    private function parseCccdText($text)
    {
        $data = [
            'cccd' => null,
            'hoTen' => null,
            'ngaySinh' => null,
            'gioiTinh' => null,
            'diaChi' => null,
        ];

        if (empty($text)) {
            return $data;
        }

        // 0. Hỗ trợ chuỗi QR CCCD gắn chip: 089204017978|...|Trương Nhựt Hào|30102004|Nam|Địa chỉ|...
        if (preg_match('/\b(\d{12})\|([^|]*)\|([^|]+)\|(\d{8})\|(Nam|Nữ)\|([^|]+)/iu', $text, $qrMatches)) {
            $dobRaw = $qrMatches[4];
            $dobFormatted = substr($dobRaw, 4, 4) . '-' . substr($dobRaw, 2, 2) . '-' . substr($dobRaw, 0, 2);
            return [
                'cccd' => $qrMatches[1],
                'hoTen' => mb_strtoupper(trim($qrMatches[3])),
                'ngaySinh' => $dobFormatted,
                'gioiTinh' => mb_strtolower($qrMatches[5]) === 'nam' ? 'Nam' : 'Nữ',
                'diaChi' => trim($qrMatches[6]),
            ];
        }

        // 1. Số CCCD (12 số)
        if (preg_match('/(?:\b|Số|So|No\.?)\s*[:\-\/\s]?\s*([0O\d][0O\d\s]{10,16}[0O\d])/iu', $text, $matches)) {
            $cleanedDigits = preg_replace('/\D/', '', str_ireplace('O', '0', $matches[1]));
            if (strlen($cleanedDigits) >= 12) {
                $data['cccd'] = substr($cleanedDigits, 0, 12);
            }
        }
        if (empty($data['cccd']) && preg_match('/\b(\d{12})\b/', $text, $matches)) {
            $data['cccd'] = $matches[1];
        }

        // 2. Ngày sinh (dd/mm/yyyy) -> YYYY-MM-DD
        if (preg_match('/\b(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})\b/', $text, $matches)) {
            $data['ngaySinh'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        // 3. Giới tính
        if (preg_match('/\b(Nam|Nữ)\b/iu', $text, $matches)) {
            $data['gioiTinh'] = mb_strtolower($matches[1]) == 'nam' ? 'Nam' : 'Nữ';
        }

        // Clean english labels helper
        $cleanLabels = function ($str) {
            $str = preg_replace('/\/?\s*Full name\s*/iu', '', $str);
            $str = preg_replace('/\/?\s*Place of origin\s*/iu', '', $str);
            $str = preg_replace('/\/?\s*Place of residence\s*/iu', '', $str);
            $str = preg_replace('/\/?\s*Date of birth\s*/iu', '', $str);
            $str = preg_replace('/\/?\s*Sex\s*/iu', '', $str);
            $str = preg_replace('/\/?\s*Nationality\s*/iu', '', $str);
            return trim($str, "/: \t\n\r\0\x0B-_¬");
        };

        // 4. Họ tên
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            if (preg_match('/(?:Họ và tên|Họ tên|Full\s*name)\s*[:\-\/]?\s*(.*)/iu', $line, $matches)) {
                $name = $cleanLabels($matches[1]);
                if ($this->isValidName($name) && strtoupper($name) !== 'FULL NAME') {
                    $data['hoTen'] = mb_strtoupper($name);
                    break;
                }
                if ($i + 1 < $lineCount) {
                    $nextLine = $cleanLabels($lines[$i + 1]);
                    if ($this->isValidName($nextLine) && !preg_match('/(Ngày sinh|Date of birth|Giới tính|Quốc tịch)/iu', $nextLine)) {
                        $data['hoTen'] = mb_strtoupper($nextLine);
                        break;
                    }
                }
            }
        }

        // 5. Nơi thường trú
        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            if (preg_match('/(?:Nơi thường trú|Place of residence|Quê quán|Nơi ở)\s*[:\-\/]?\s*(.*)/iu', $line, $matches)) {
                $addr = $cleanLabels($matches[1]);
                if ($i + 1 < $lineCount) {
                    $nextAddr = $cleanLabels($lines[$i + 1]);
                    if (!empty($nextAddr) && !preg_match('/(Có giá trị|Date of expiry|Quốc tịch)/iu', $nextAddr)) {
                        $addr = trim($addr . ', ' . $nextAddr, ', ');
                    }
                }
                if (strlen($addr) > 5 && !str_contains(strtoupper($addr), 'PLACE OF')) {
                    $data['diaChi'] = $addr;
                    break;
                }
            }
        }

        return $data;
    }
}

