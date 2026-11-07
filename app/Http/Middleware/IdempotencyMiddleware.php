<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $requestHash = hash('sha256', $request->getContent());

        try {
            $record = null;

            // Truy vấn an toàn kiểm tra token chống trùng lặp
            try {
                $record = DB::table('idempotencyKeys')
                    ->where(function ($q) use ($idempotencyKey) {
                        $q->where('idempotency_key', $idempotencyKey);
                    })
                    ->first();
            } catch (\Throwable $e1) {
                try {
                    $record = DB::table('idempotency_keys')
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                } catch (\Throwable $e2) {
                    try {
                        $record = IdempotencyKey::where('idempotencyKey', $idempotencyKey)->first();
                    } catch (\Throwable $e3) {
                        $record = null;
                    }
                }
            }

            if ($record) {
                $recHash = $record->request_hash ?? $record->requestHash ?? null;
                $recExpires = $record->expires_at ?? $record->expiresAt ?? null;
                $recPayload = $record->response_payload ?? $record->responsePayload ?? '';
                $recStatus = (int)($record->status_code ?? $record->statusCode ?? 200);

                if ($recExpires && Carbon::parse($recExpires)->isPast()) {
                    try {
                        DB::table('idempotencyKeys')->where('id', $record->id)->delete();
                    } catch (\Throwable $eDel) {}
                } else {
                    if ($recHash && $recHash !== $requestHash) {
                        return response()->json(['error' => 'Idempotency key already used with different payload'], 400);
                    }

                    if (!empty($recPayload)) {
                        return response($recPayload, $recStatus > 0 ? $recStatus : 200)
                            ->header('Idempotency-Key', $idempotencyKey)
                            ->header('Content-Type', 'application/json')
                            ->header('X-Idempotent-Cache', 'HIT');
                    }
                }
            }
        } catch (\Throwable $eCheck) {
            Log::warning('IdempotencyMiddleware check error: ' . $eCheck->getMessage());
        }

        // Process request
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            try {
                $payload = method_exists($response, 'getContent') ? $response->getContent() : '';
                $statusCode = $response->getStatusCode();
                $expiresAt = Carbon::now()->addHours(24)->toDateTimeString();

                // Lưu idempotency key an toàn
                try {
                    DB::table('idempotencyKeys')->updateOrInsert(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'request_path' => $request->path(),
                            'request_hash' => $requestHash,
                            'response_payload' => $payload,
                            'status_code' => $statusCode,
                            'expires_at' => $expiresAt,
                            'created_at' => Carbon::now()->toDateTimeString(),
                            'updated_at' => Carbon::now()->toDateTimeString(),
                        ]
                    );
                } catch (\Throwable $eIns1) {
                    try {
                        DB::table('idempotency_keys')->updateOrInsert(
                            ['idempotency_key' => $idempotencyKey],
                            [
                                'request_path' => $request->path(),
                                'request_hash' => $requestHash,
                                'response_payload' => $payload,
                                'status_code' => $statusCode,
                                'expires_at' => $expiresAt,
                                'created_at' => Carbon::now()->toDateTimeString(),
                                'updated_at' => Carbon::now()->toDateTimeString(),
                            ]
                        );
                    } catch (\Throwable $eIns2) {
                        // Không bao giờ để lỗi lưu cache idempotency làm ảnh hưởng đến đơn hàng của khách
                    }
                }
            } catch (\Throwable $eSave) {
                Log::warning('IdempotencyKey save error: ' . $eSave->getMessage());
            }
        }

        if (method_exists($response, 'header')) {
            $response->header('Idempotency-Key', $idempotencyKey);
        }

        return $response;
    }
}
