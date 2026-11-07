<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PageViewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'page_path' => ['required', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
        ]);

        // Chỉ chấp nhận đường dẫn nội bộ, không nhận URL bên ngoài.
        $pagePath = $validated['page_path'];

        if (
            !str_starts_with($pagePath, '/') ||
            str_starts_with($pagePath, '//')
        ) {
            return response()->json([
                'message' => 'Invalid page path',
            ], 422);
        }

        $visitId = $request->header('X-Visit-ID');

        // Chỉ chấp nhận UUID hợp lệ.
        if (!is_string($visitId) || !\Illuminate\Support\Str::isUuid($visitId)) {
            $visitId = null;
        }

        $event = [
            'event_type' => 'page_view',
            'timestamp' => now()->toIso8601String(),
            'visit_id' => $visitId,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'method' => 'PAGE_VIEW',
            'path' => $pagePath,
            'page_title' => $validated['page_title'] ?? null,
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

        return response()->json([
            'success' => true,
        ]);
    }
}