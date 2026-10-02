<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietHoaDonNhap;
use App\Models\ChuKySo;
use App\Models\HoaDonNhap;
use App\Models\LoHang;
use App\Models\NhaCungCap;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HoaDonNhapController extends Controller
{
    public function index(Request $request)
    {
        $invoices = HoaDonNhap::with(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'chuKySos.nguoiKy', 'chiTiets.sanPham', 'chiTiets.loHang'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách hóa đơn nhập hàng thành công',
            'data' => $invoices,
        ]);
    }

    public function store(Request $request)
    {
        $userId = auth('api')->id() ?? $request->input('nguoi_nhap_id') ?? 1;

        $request->merge([
            'nhaCungCapId' => $request->input('nhaCungCapId') ?? $request->input('MANCC'),
            'cuaHangId' => $request->input('cuaHangId') ?? $request->input('MACUAHANG_NHAP') ?? $request->input('MACUAHANG'),
            'nguoi_nhap_id' => $userId,
            'ghiChu' => $request->input('ghiChu') ?? $request->input('GHICHU'),
        ]);

        $rawItems = $request->input('items') ?? $request->input('chi_tiet') ?? [];
        $items = array_map(function ($item) {
            return [
                'sanPhamId' => $item['sanPhamId'] ?? $item['MASP'] ?? null,
                'soLuong' => $item['soLuong'] ?? $item['SOLUONGTN'] ?? $item['SOLUONGTCT'] ?? $item['SOLUONG'] ?? 1,
                'don_gia_nhap' => $item['don_gia_nhap'] ?? $item['DONGIANHAP'] ?? 0,
                'ngaySanXuat' => $item['ngaySanXuat'] ?? $item['NGAYSX'] ?? $item['NGAYSANXUAT'] ?? $item['ngaySanXuat'] ?? null,
                'hsd' => $item['hsd'] ?? $item['HSD_LO'] ?? $item['HSD'] ?? $item['HANSUDUNG'] ?? $item['han_su_dung'] ?? null,
            ];
        }, $rawItems);

        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'nhaCungCapId' => 'required|exists:nhaCungCap,id',
            'cuaHangId' => 'required|exists:cuahang,id',
            'nguoi_nhap_id' => 'required|exists:nguoidung,id',
            'ghiChu' => 'nullable|string',
            'auto_sign_all' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:sanPham,id',
            'items.*.so_luong' => 'required|integer|min:1',
            'items.*.don_gia_nhap' => 'required|numeric|min:0',
            'items.*.ngay_san_xuat' => 'nullable|date',
            'items.*.hsd' => 'required|date',
        ]);

        $autoSign = $request->get('auto_sign_all', true) ? 1 : 0;
        $kyNcc = $request->input('ky_ncc');

        try {
            DB::statement('CALL sp_create_hoadon_nhap(?, ?, ?, ?, ?, ?, ?, @hoadon_id, @ma_hoadon, @error)', [
                $validated['nhaCungCapId'],
                $validated['cuaHangId'],
                $validated['nguoi_nhap_id'],
                $validated['ghiChu'] ?? null,
                $autoSign,
                $kyNcc ?? null,
                json_encode($validated['items']),
            ]);

            $res = DB::select('SELECT @hoadon_id AS hoadon_id, @ma_hoadon AS ma_hoadon, @error AS error')[0];

            if ($res->error) {
                return response()->json(['success' => false, 'message' => $res->error], 500);
            }

            $invoice = HoaDonNhap::find($res->hoadon_id);

            return response()->json([
                'success' => true,
                'message' => $autoSign ? 'Khởi tạo hóa đơn nhập & tự động hoàn tất ký 4 bên thành công' : 'Khởi tạo hóa đơn nhập thành công, đã gửi yêu cầu trình ký cho 4 bên',
                'data' => $invoice->load(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi khởi tạo hóa đơn nhập: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $invoice = HoaDonNhap::with(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'chuKySos.nguoiKy', 'chiTiets.sanPham', 'chiTiets.loHang'])->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn nhập hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết hóa đơn nhập hàng',
            'data' => $invoice,
        ]);
    }
}
