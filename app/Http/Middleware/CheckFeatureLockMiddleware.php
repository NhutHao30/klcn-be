<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemFeatureLock;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureLockMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $featureCode
     */
    public function handle(Request $request, Closure $next, string $featureCode): Response
    {
        $feature = SystemFeatureLock::where('maChucNang', $featureCode)->first();

        if ($feature && $feature->isLocked) {
            $reason = $feature->lyDoKhoa ? " Lý do: {$feature->lyDoKhoa}." : "";
            return response()->json([
                'success' => false,
                'isFeatureLocked' => true,
                'maChucNang' => $featureCode,
                'tenChucNang' => $feature->tenChucNang,
                'message' => "Chức năng \"{$feature->tenChucNang}\" hiện đang được tạm khóa để nâng cấp / bảo trì hệ thống.{$reason} Vui lòng thử lại sau!",
            ], 423); // 423 Locked
        }

        return $next($request);
    }
}
