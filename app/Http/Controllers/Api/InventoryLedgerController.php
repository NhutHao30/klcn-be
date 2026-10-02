<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NhatKyKho;

class InventoryLedgerController extends Controller
{
    public function index(Request $request)
    {
        $query = NhatKyKho::with(['cuahang', 'sanpham']);

        if ($request->filled('cuaHangId')) {
            $query->where('cuaHangId', $request->cuaHangId);
        }

        if ($request->filled('sanPhamId')) {
            $query->where('sanPhamId', $request->sanPhamId);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(15);
        return response()->json($logs);
    }
}
