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
        $url = $request->query('url');
        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        // Thử lấy file qua Storage::disk('s3') trước (đáng tin cậy hơn trong Docker)
        try {
            $bucket = env('AWS_BUCKET', 'dolabakery');
            $path = parse_url($url, PHP_URL_PATH);
            
            // Remove /bucket/ prefix
            $prefix = "/$bucket/";
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            } else {
                $path = ltrim($path, '/');
            }

            if (Storage::disk('s3')->exists($path)) {
                $content = Storage::disk('s3')->get($path);
                $mimeType = Storage::disk('s3')->mimeType($path);
                
                return response($content, 200)
                    ->header('Content-Type', $mimeType ?: 'image/webp')
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        } catch (\Exception $e) {
            // Fallthrough to HTTP attempt
        }

        // Fallback: thử lấy qua HTTP trực tiếp
        try {
            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', $response->header('Content-Type'))
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        } catch (\Exception $e) {
            // Fallthrough to 404
        }

        return response()->json(['error' => 'Image not found'], 404);
    }
}
