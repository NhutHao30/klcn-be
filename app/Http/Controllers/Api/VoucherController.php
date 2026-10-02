<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voucher;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::where('trangThai', 1)
            ->where('ngay_ket_thuc', '>=', now())
            ->get();
        return response()->json($vouchers);
    }

    public function show($code)
    {
        $voucher = Voucher::where('ma_voucher', $code)->where('trangThai', 1)->first();
        if (!$voucher || !$voucher->isValid()) {
            return response()->json(['error' => 'Mã Voucher không tồn tại hoặc đã hết hạn'], 404);
        }
        return response()->json($voucher);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ma_voucher' => 'required|string|unique:voucher,ma_voucher',
            'loai_giam_gia' => 'required|integer|in:0,1',
            'gia_tri_giam' => 'required|numeric|min:0',
            'don_hang_toi_thieu' => 'required|numeric|min:0',
            'soLuong' => 'required|integer|min:1',
            'ngay_bat_dau' => 'required|date',
            'ngay_ket_thuc' => 'required|date|after:ngay_bat_dau',
        ]);

        $voucher = Voucher::create([
            'ma_voucher' => strtoupper($request->ma_voucher),
            'loai_giam_gia' => $request->loai_giam_gia,
            'gia_tri_giam' => $request->gia_tri_giam,
            'don_hang_toi_thieu' => $request->don_hang_toi_thieu,
            'giam_toi_da' => $request->giam_toi_da ?? null,
            'soLuong' => $request->soLuong,
            'da_su_dung' => 0,
            'ngay_bat_dau' => $request->ngay_bat_dau,
            'ngay_ket_thuc' => $request->ngay_ket_thuc,
            'trangThai' => 1,
        ]);

        return response()->json(['message' => 'Tạo Voucher thành công', 'data' => $voucher], 201);
    }
}
