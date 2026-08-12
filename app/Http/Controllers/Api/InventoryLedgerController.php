<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryMovement;

class InventoryLedgerController extends Controller
{
    public function getMovements(Request $request)
    {
        $query = InventoryMovement::with(['sanpham', 'user', 'cuahang'])->orderBy('created_at', 'desc');

        if ($request->has('store_id')) {
            $query->where('MACUAHANG', $request->store_id);
        }

        if ($request->has('product_id')) {
            $query->where('MASP', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate(20));
    }
}
