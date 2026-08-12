<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KhuyenMai;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = KhuyenMai::orderBy('created_at', 'desc')->get();
        return response()->json($vouchers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'MA_VOUCHER' => 'required|string|unique:khuyenmai,MA_VOUCHER',
            'TEN_VOUCHER' => 'required|string',
            'LOAI_GIAM' => 'required|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|integer|min:1',
            'DON_TOI_THIEU' => 'required|integer|min:0',
            'GIAM_TOI_DA' => 'nullable|integer|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required|date',
            'NGAY_KET_THUC' => 'required|date|after:NGAY_BAT_DAU',
            'TRANG_THAI' => 'boolean'
        ]);

        $voucher = KhuyenMai::create($request->all());

        return response()->json(['message' => 'Tạo voucher thành công', 'data' => $voucher], 201);
    }

    public function show($id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }
        return response()->json($voucher);
    }

    public function update(Request $request, $id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        $request->validate([
            'TEN_VOUCHER' => 'required|string',
            'LOAI_GIAM' => 'required|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|integer|min:1',
            'DON_TOI_THIEU' => 'required|integer|min:0',
            'GIAM_TOI_DA' => 'nullable|integer|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required|date',
            'NGAY_KET_THUC' => 'required|date|after:NGAY_BAT_DAU',
            'TRANG_THAI' => 'boolean'
        ]);

        $voucher->update($request->all());

        return response()->json(['message' => 'Cập nhật voucher thành công', 'data' => $voucher]);
    }

    public function destroy($id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        // Thay vi xoa, ta chi doi TRANG_THAI
        $voucher->TRANG_THAI = !$voucher->TRANG_THAI;
        $voucher->save();

        $msg = $voucher->TRANG_THAI ? 'Đã kích hoạt voucher' : 'Đã vô hiệu hóa voucher';
        return response()->json(['message' => $msg]);
    }
}
