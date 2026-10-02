<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\IdempotencyKey;
use Carbon\Carbon;

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

        $record = IdempotencyKey::where('idempotencyKey', $idempotencyKey)->first();
        $requestHash = hash('sha256', $request->getContent());

        if ($record) {
            if ($record->expiresAt && Carbon::parse($record->expiresAt)->isPast()) {
                $record->delete();
            } else {
                if ($record->requestHash !== $requestHash) {
                    return response()->json(['error' => 'Idempotency key already used with different payload'], 400);
                }

                return response($record->responsePayload, $record->statusCode ?? 200)
                    ->header('Idempotency-Key', $idempotencyKey)
                    ->header('Content-Type', 'application/json')
                    ->header('X-Idempotent-Cache', 'HIT');
            }
        }

        // Process request
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            try {
                IdempotencyKey::create([
                    'idempotencyKey' => $idempotencyKey,
                    'requestPath' => $request->path(),
                    'requestHash' => $requestHash,
                    'responsePayload' => method_exists($response, 'getContent') ? $response->getContent() : '',
                    'statusCode' => $response->getStatusCode(),
                    'expiresAt' => Carbon::now()->addHours(24),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('IdempotencyKey save error: ' . $e->getMessage());
            }
        }

        if (method_exists($response, 'header')) {
            $response->header('Idempotency-Key', $idempotencyKey);
        }

        return $response;
    }
}

