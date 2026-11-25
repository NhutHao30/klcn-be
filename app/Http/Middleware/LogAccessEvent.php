<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogAccessEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        // Lấy mã phiên do React gửi lên.
        $visitId = $request->header('X-Visit-ID');

        if (!is_string($visitId) || !Str::isUuid($visitId)) {
            $visitId = null;
        }

        // Lấy Content-Type của response.
        $contentType = $response->headers->get('Content-Type');

        // Lấy kích thước nội dung response nếu có thể đọc được.
        $content = $response->getContent();

        $responseSizeBytes = is_string($content)
            ? strlen($content)
            : null;

        $event = [
            'timestamp' => now()->toIso8601String(),
            'visit_id' => $visitId,
            'request_id' => (string) Str::uuid(),
            'method' => strtoupper($request->method()),
            'path' => '/' . ltrim($request->path(), '/'),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_agent' => (string) $request->userAgent(),
            'referer' => (string) $request->headers->get('referer', ''),
            'content_type' => $contentType,
            'response_size_bytes' => $responseSizeBytes,
        ];

        $directory = storage_path('logs');

        File::ensureDirectoryExists($directory);

        File::append(
            $directory . DIRECTORY_SEPARATOR . 'access-events.jsonl',
            json_encode(
                $event,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . PHP_EOL
        );

        return $response;
    }
}