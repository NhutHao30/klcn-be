<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImageProxyController extends Controller
{
    public function show(Request $request)
    {
        $url = $request->query('url') ?? $request->query('path');
        if (!$url) {
            return response()->json(['error' => 'URL or path is required'], 400);
        }

        // Thử lấy file qua Storage::disk('s3') (MinIO)
        try {
            $bucket = env('AWS_BUCKET', 'dolabakery');
            $parsedPath = parse_url($url, PHP_URL_PATH) ?? $url;
            
            // Remove /bucket/ or /storage/ prefix if present
            $prefixBucket = "/$bucket/";
            $prefixStorage = "/storage/";
            if (str_starts_with($parsedPath, $prefixBucket)) {
                $path = substr($parsedPath, strlen($prefixBucket));
            } else if (str_starts_with($parsedPath, $prefixStorage)) {
                $path = substr($parsedPath, strlen($prefixStorage));
            } else {
                $path = ltrim($parsedPath, '/');
            }

            if (Storage::disk('s3')->exists($path)) {
                $content = Storage::disk('s3')->get($path);
                $mimeType = Storage::disk('s3')->mimeType($path);
                
                return response($content, 200)
                    ->header('Content-Type', $mimeType ?: 'image/jpeg')
                    ->header('Cache-Control', 'private, max-age=3600');
            }
        } catch (\Exception $e) {
            // Fallthrough to HTTP attempt
        }

        // Fallback 1: Thử lấy qua HTTP trực tiếp nếu là URL tuyệt đối
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            try {
                $response = Http::timeout(5)->get($url);
                if ($response->successful()) {
                    return response($response->body(), 200)
                        ->header('Content-Type', $response->header('Content-Type'))
                        ->header('Cache-Control', 'private, max-age=3600');
                }
            } catch (\Exception $e) {
                // Fallthrough to local asset check
            }
        }

        // Fallback 2: Thử tìm trong public/assets/IMG/ theo tên file
        $basename = basename($path ?? $url);
        $localPath = public_path('assets/IMG/' . $basename);
        if (file_exists($localPath) && is_file($localPath)) {
            $mime = mime_content_type($localPath) ?: 'image/webp';
            return response()->file($localPath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        // Fallback 3: Trả về ảnh bánh mặc định thay vì trả về JSON lỗi 404
        $defaultImg = public_path('assets/IMG/productnew2.webp');
        if (file_exists($defaultImg)) {
            return response()->file($defaultImg, [
                'Content-Type' => 'image/webp',
                'Cache-Control' => 'public, max-age=3600'
            ]);
        }

        return redirect('https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=500&auto=format&fit=crop&q=80');
    }
}
