<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $userId = null;
        if (Auth::guard('api')->check()) {
            $userId = Auth::guard('api')->user()->USERNAME;
        }

        $record = IdempotencyKey::where('idempotency_key', $idempotencyKey)->first();
        $requestHash = hash('sha256', $request->getContent());

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                return response()->json(['error' => 'Idempotency key already used with different payload'], 400);
            }

            if ($record->status === 'processing') {
                return response()->json(['error' => 'Request is already being processed'], 409);
            }

            if ($record->status === 'completed') {
                return response($record->response_body, $record->response_code)
                    ->header('Idempotency-Key', $idempotencyKey)
                    ->header('Content-Type', 'application/json')
                    ->header('X-Idempotent-Cache', 'HIT');
            }
        }

        // Chưa có thì tạo mới (status processing)
        $record = IdempotencyKey::create([
            'idempotency_key' => $idempotencyKey,
            'USERNAME' => $userId,
            'endpoint' => $request->path(),
            'request_hash' => $requestHash,
            'status' => 'processing',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // Xử lý request
        $response = $next($request);

        // Update kết quả
        $record->update([
            'status' => 'completed',
            'response_code' => $response->getStatusCode(),
            'response_body' => method_exists($response, 'getContent') ? $response->getContent() : '',
        ]);

        if (method_exists($response, 'header')) {
            $response->header('Idempotency-Key', $idempotencyKey);
        }

        return $response;
    }
}
