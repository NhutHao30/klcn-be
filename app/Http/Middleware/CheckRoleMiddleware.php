<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRoleMiddleware
{
    /**
     * Check access permissions (Admin: 0, Manager: 1, Staff: 2, Customer: 3)
     * You can pass multiple roles using commas: middleware('role:0,1')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Bạn chưa đăng nhập.'], 401);
        }

        $user = Auth::guard('api')->user();
        $maRole = (string)$user->MAROLE;
        $vaitroId = (string)$user->vaiTroId;

        // Admin (MAROLE = 0 hoặc vaitro_id = 1) có full quyền truy cập các route Admin
        if ($maRole === '0' || $vaitroId === '1') {
            return $next($request);
        }

        // Flatten any comma-delimited role parameters
        $flatRoles = [];
        foreach ($roles as $r) {
            foreach (explode(',', (string)$r) as $part) {
                $trim = trim($part);
                if ($trim !== '') {
                    $flatRoles[] = $trim;
                }
            }
        }

        if (in_array($maRole, $flatRoles) || in_array($vaitroId, $flatRoles)) {
            return $next($request);
        }

        return response()->json(['error' => 'Bạn không có quyền truy cập vào chức năng này.'], 403);
    }
}
