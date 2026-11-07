<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NhatKyKho;
use Illuminate\Support\Facades\Auth;

class InventoryLedgerController extends Controller
{
    public function index(Request $request)
    {
        return $this->getMovements($request);
    }

    public function getMovements(Request $request)
    {
        \App\Services\InventoryLedgerService::ensureSchema();
        $user = Auth::guard('api')->user();
        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                try {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                } catch (\Exception $e) {}
            }
        }

        $query = NhatKyKho::with(['cuahang', 'sanpham']);

        $storeId = $request->input('cuaHangId') ?? $request->input('macuahang') ?? $request->input('store_id') ?? $request->input('storeId');

        // Automatic store scoping for branch managers and staff
        if (!$storeId && $user && $user->nguoidung) {
            if ((int)$user->vaiTroId !== 1 && strtolower($user->username) !== 'admin' && (int)$user->vaiTroId !== 8) {
                $storeId = $user->nguoidung->cuaHangId;
            }
        }

        if ($storeId) {
            $query->where('cuaHangId', $storeId);
        }

        if ($request->filled('sanPhamId')) {
            $query->where('sanPhamId', $request->sanPhamId);
        }

        if ($request->filled('loaiBienDong')) {
            $query->where('loaiBienDong', $request->loaiBienDong);
        }

        $perPage = (int)$request->input('per_page', 15);
        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        // Map snake_case / compatibility fields for UI consumption
        $logs->getCollection()->transform(function ($item) {
            $item->so_luong_thay_doi = $item->soLuongThayDoi;
            $item->so_luong_ton_sau = $item->soLuongTonSau;
            $item->loai_bien_dong = $item->loaiBienDong;
            $item->ma_tham_chieu = $item->maThamChieu;
            $item->quantity = $item->soLuongThayDoi;
            $item->after_quantity = $item->soLuongTonSau;
            $item->type = $item->loaiBienDong;
            $item->reference_id = $item->maThamChieu;
            return $item;
        });

        return response()->json($logs);
    }
}
