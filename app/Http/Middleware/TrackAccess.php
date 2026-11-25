<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackAccess
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('start', microtime(true));
        return $next($request);
    }

    // terminate() chạy SAU khi đã trả response cho user, không làm chậm web
    public function terminate(Request $request, $response)
    {
        // Bỏ qua preflight CORS
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        $path = '/' . ltrim($request->path(), '/');

        
        $skipPrefixes = ['/api/track', '/api/proxy-image', '/build', '/favicon'];
        foreach ($skipPrefixes as $prefix) {
         if (str_starts_with($path, $prefix)) {
            return;
         }
        }

        // Lấy thời điểm bắt đầu an toàn, tránh ra số khổng lồ khi thiếu 'start'
        $start = $request->attributes->get('start');
        $responseTime = $start ? (int) round((microtime(true) - $start) * 1000) : 0;
        $responseTime = max(0, min($responseTime, 600000)); // tối đa 10 phút

        $userId = optional($request->user())->id;

        $payload = [
            'event_id'         => (string) Str::uuid(),
            'event_time'       => now()->utc()->format('Y-m-d H:i:s'),
            'session_id'       => $request->header('X-Visit-ID', $request->header('X-Session-Id', 'none')),
            'user_id'          => is_numeric($userId) ? (int) $userId : null,
            'ip'               => $request->ip(),
            'method'           => $request->method(),
            'path'             => $path,
            'query'            => $request->getQueryString() ?? '',
            'status'           => $response->getStatusCode(),
            'response_time_ms' => $responseTime,
            'user_agent'       => $request->userAgent() ?? '',
            'referer'          => (string) $request->headers->get('referer', ''),
            'event_type'       => 'api_call',
        ];

        try {
            Http::timeout(1)->asJson()->post(env('NIFI_URL', 'http://localhost:8081/events'), $payload);
        } catch (\Throwable $e) {
            // Không để lỗi NiFi làm hỏng website
            Log::warning('NiFi send failed: ' . $e->getMessage());
        }
    }
}