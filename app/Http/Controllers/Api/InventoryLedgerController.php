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

        if ($request->filled('cuahang_id')) {
            $query->where('cuahang_id', $request->cuahang_id);
        }

        if ($request->filled('sanpham_id')) {
            $query->where('sanpham_id', $request->sanpham_id);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(15);
        return response()->json($logs);
    }
}
