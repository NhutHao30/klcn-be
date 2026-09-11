<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietDatHangTruoc;
use App\Models\DatHangTruoc;
use App\Models\HdBan;
use App\Models\ChiTietHdBan;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\SanPham;
use App\Models\TonKhoCuaHang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DatHangTruocController extends Controller
{
    // =========================================================
    // Helpers
    // =========================================================

    /** Lấy mã cửa hàng của nhân viên đang đăng nhập (mặc định 1 nếu là Super Admin / chưa gán chi nhánh) */
    private function getStoreId(): int
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            $nhanVien = NhanVien::where('USERNAME', $user->USERNAME)->first();
            if ($nhanVien && !empty($nhanVien->MACUAHANG)) {
                return (int) $nhanVien->MACUAHANG;
            }
        }
        return 1;
    }

    /** Format 1 đơn DHT thành mảng response */
    private function formatOrder(DatHangTruoc $dht): array
    {
        return [
            'maDHT'           => $dht->MADHT,
            'ngayDat'         => $dht->NGAY_DAT,
            'ngayHenLay'      => $dht->NGAY_HEN_LAY,
            'hoTenKH'         => $dht->HOTEN_KH,
            'sdtKH'           => $dht->SDT_KH,
            'maKH'            => $dht->MAKH,
            'lyDo'            => $dht->LY_DO_DAT_TRUOC,
            'ghiChu'          => $dht->GHI_CHU,
            'tongTien'        => $dht->TONG_TIEN,
            'tienCoc'         => $dht->TIEN_COC,
            'tienConLai'      => $dht->tien_con_lai,
            'phuongThucCoc'   => $dht->PHUONG_THUC_COC,
            'trangThai'       => $dht->TRANG_THAI,
            'maHDHoanThanh'   => $dht->MAHD_HOAN_THANH,
            'usernameNV'      => $dht->USERNAME_NV,
            'chuKy'           => $dht->CHU_KY,
            'hinhThucNhan'    => $dht->HINH_THUC_NHAN ?? 'tai_cua_hang',
            'diaChiGiao'      => $dht->DIA_CHI_GIAO,
            'phiGiaoHang'     => (int) ($dht->PHI_GIAO_HANG ?? 0),
            'cuaHang'         => $dht->cuaHang ? [
                'maCuaHang'  => $dht->cuaHang->MACUAHANG,
                'tenCuaHang' => $dht->cuaHang->TENCUAHANG,
                'diaChi'     => $dht->cuaHang->DIACHI,
            ] : null,
            'chiTiets' => $dht->chiTiets->map(fn($ct) => [
                'id'        => $ct->id,
                'maSP'      => $ct->MASP,
                'tenSP'     => $ct->TEN_SP,
                'soLuong'   => $ct->SO_LUONG,
                'donGia'    => $ct->DON_GIA,
                'thanhTien' => $ct->thanh_tien,
                'ghiChuSP'  => $ct->GHI_CHU_SP,
            ])->values(),
        ];
    }

    // =========================================================
    // GET /admin/pre-orders — Danh sách
    // =========================================================
    public function index(Request $request)
    {
        $user     = Auth::guard('api')->user();
        $storeId  = $this->getStoreId();

        $query = DatHangTruoc::with(['cuaHang', 'chiTiets']);

        // Nhân viên (role 2) chỉ xem đơn của cửa hàng mình
        if ($user && $user->MAROLE == 2 && $storeId) {
            $query->where('MACUAHANG', $storeId);
        }
        // Quản lý chi nhánh (role 1) cũng chỉ xem của chi nhánh
        if ($user && $user->MAROLE == 1 && $storeId) {
            $query->where('MACUAHANG', $storeId);
        }

        // Filter trạng thái
        if ($request->filled('trangThai')) {
            $query->where('TRANG_THAI', $request->trangThai);
        }

        // Tìm theo mã DHT hoặc SĐT KH
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('MADHT', 'like', "%{$s}%")
                  ->orWhere('HOTEN_KH', 'like', "%{$s}%")
                  ->orWhere('SDT_KH', 'like', "%{$s}%");
            });
        }

        $size   = $request->input('size', 50);
        $result = $query->orderBy('NGAY_DAT', 'desc')->paginate($size);

        return response()->json([
            'content'       => collect($result->items())->map(fn($d) => $this->formatOrder($d))->values(),
            'totalElements' => $result->total(),
            'totalPages'    => $result->lastPage(),
        ]);
    }

    // =========================================================
    // POST /admin/pre-orders — Tạo đơn đặt trước
    // =========================================================
    public function store(Request $request)
    {
        // Chuẩn hóa makh & masp (chuyển chuỗi rỗng thành null)
        if ($request->has('makh') && trim((string)$request->makh) === '') {
            $request->merge(['makh' => null]);
        }

        // Kiểm tra xem makh có thực sự tồn tại trong DB không
        if ($request->filled('makh')) {
            $khExists = KhachHang::where('MAKH', $request->makh)->exists();
            if (!$khExists) {
                $request->merge(['makh' => null]);
            }
        }

        // Chuẩn hóa chi_tiet masp
        if ($request->has('chi_tiet') && is_array($request->chi_tiet)) {
            $chiTiet = $request->chi_tiet;
            foreach ($chiTiet as $idx => $item) {
                if (isset($item['masp']) && trim((string)$item['masp']) === '') {
                    $chiTiet[$idx]['masp'] = null;
                }
            }
            $request->merge(['chi_tiet' => $chiTiet]);
        }

        $request->validate([
            'hoten_kh'          => 'required|string|max:100',
            'sdt_kh'            => 'required|string|max:20',
            'makh'              => 'nullable|string',
            'ngay_hen_lay'      => 'required|date',
            'ly_do_dat_truoc'   => 'required|in:het_hang,hen_ngay',
            'ghi_chu'           => 'nullable|string|max:1000',
            'phuong_thuc_coc'   => 'nullable|string|max:50',
            'tien_coc'          => 'nullable|integer|min:0',
            'chu_ky'            => 'nullable|string',
            'hinh_thuc_nhan'   => 'nullable|in:tai_cua_hang,giao_tan_noi',
            'dia_chi_giao'     => 'nullable|string|max:255',
            'phi_giao_hang'    => 'nullable|integer|min:0',
            'chi_tiet'          => 'required|array|min:1',
            'chi_tiet.*.ten_sp' => 'required|string|max:255',
            'chi_tiet.*.masp'   => 'nullable|string',
            'chi_tiet.*.so_luong' => 'required|integer|min:1',
            'chi_tiet.*.don_gia'  => 'required|integer|min:0',
            'chi_tiet.*.ghi_chu_sp' => 'nullable|string|max:500',
        ]);

        // Hình thức nhận & phí giao hàng
        $hinhThucNhan = $request->hinh_thuc_nhan ?? 'tai_cua_hang';
        $phiGiaoHang  = $hinhThucNhan === 'giao_tan_noi' ? (int) ($request->phi_giao_hang ?? 0) : 0;

        // Tính tổng tiền bánh & tổng tiền đơn (bao gồm phí giao hàng)
        $tongTienBanh = 0;
        foreach ($request->chi_tiet as $item) {
            $tongTienBanh += $item['so_luong'] * $item['don_gia'];
        }
        $tongTien = $tongTienBanh + $phiGiaoHang;

        // --- Quy tắc đặt cọc ---
        // cọc bánh nếu > 200k = 50,000đ; giao tận nơi = cọc bánh + phí ship GHN
        $minCocBanh = $tongTienBanh > 200000 ? 50000 : 0;
        $minCocRequired = $hinhThucNhan === 'giao_tan_noi' ? ($minCocBanh + $phiGiaoHang) : $minCocBanh;

        $tienCoc = (int) ($request->tien_coc ?? 0);

        if ($minCocRequired > 0 && $tienCoc < $minCocRequired) {
            $msg = $hinhThucNhan === 'giao_tan_noi'
                ? "Giao hàng tận nơi yêu cầu đặt cọc tối thiểu " . number_format($minCocRequired) . "đ (" . ($minCocBanh > 0 ? number_format($minCocBanh) . "đ cọc bánh + " : "") . number_format($phiGiaoHang) . "đ phí ship GHN)."
                : "Đơn trên 200,000đ phải đặt cọc tối thiểu 50,000đ.";
            return response()->json([
                'message' => $msg,
                'errors'  => ['tien_coc' => [$msg]],
            ], 422);
        }

        if ($tienCoc >= $tongTien && $tongTien > 0) {
            return response()->json([
                'message' => 'Tiền cọc không được lớn hơn hoặc bằng tổng tiền.',
                'errors'  => ['tien_coc' => ['Tiền cọc phải nhỏ hơn tổng tiền.']],
            ], 422);
        }

        // Trạng thái ban đầu
        $trangThai = $tienCoc > 0 ? 'da_coc' : 'cho_xac_nhan';

        // Xác định cửa hàng
        $storeId = $this->getStoreId() ?? 1;

        // Sinh mã DHT
        $madht = 'DHT' . now()->format('YmdHis') . rand(10, 99);

        // Nhân viên tạo
        $user = Auth::guard('api')->user();

        DB::beginTransaction();
        try {
            $dht = DatHangTruoc::create([
                'MADHT'            => $madht,
                'MACUAHANG'        => $storeId,
                'USERNAME_NV'      => $user->USERNAME ?? null,
                'MAKH'             => $request->makh ?? null,
                'HOTEN_KH'         => $request->hoten_kh,
                'SDT_KH'           => $request->sdt_kh,
                'NGAY_DAT'         => now(),
                'NGAY_HEN_LAY'     => $request->ngay_hen_lay,
                'GHI_CHU'          => $request->ghi_chu,
                'TONG_TIEN'        => $tongTien,
                'TIEN_COC'         => $tienCoc,
                'PHUONG_THUC_COC'  => $tienCoc > 0 ? ($request->phuong_thuc_coc ?? 'Tiền mặt') : null,
                'LY_DO_DAT_TRUOC'  => $request->ly_do_dat_truoc,
                'HINH_THUC_NHAN'   => $hinhThucNhan,
                'DIA_CHI_GIAO'     => $hinhThucNhan === 'giao_tan_noi' ? $request->dia_chi_giao : null,
                'PHI_GIAO_HANG'    => $phiGiaoHang,
                'TRANG_THAI'       => $trangThai,
                'CHU_KY'           => $request->chu_ky,
            ]);

            foreach ($request->chi_tiet as $item) {
                ChiTietDatHangTruoc::create([
                    'MADHT'      => $madht,
                    'MASP'       => $item['masp'] ?? null,
                    'TEN_SP'     => $item['ten_sp'],
                    'SO_LUONG'   => $item['so_luong'],
                    'DON_GIA'    => $item['don_gia'],
                    'GHI_CHU_SP' => $item['ghi_chu_sp'] ?? null,
                ]);
            }

            DB::commit();

            $dht->load(['cuaHang', 'chiTiets']);
            return response()->json([
                'message' => 'Tạo đơn đặt trước thành công.',
                'data'    => $this->formatOrder($dht),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi tạo đơn: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    // GET /admin/pre-orders/{id} — Chi tiết (dùng để in phiếu)
    // =========================================================
    public function show($id)
    {
        $dht = DatHangTruoc::with(['cuaHang', 'chiTiets', 'khachHang'])->find($id);
        if (!$dht) {
            return response()->json(['message' => 'Không tìm thấy đơn đặt trước.'], 404);
        }
        return response()->json($this->formatOrder($dht));
    }

    // =========================================================
    // PUT /admin/pre-orders/{id}/status — Cập nhật trạng thái
    // =========================================================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'trang_thai' => 'required|in:san_sang,da_huy,cho_xac_nhan,da_coc',
        ]);

        $dht = DatHangTruoc::find($id);
        if (!$dht) {
            return response()->json(['message' => 'Không tìm thấy đơn đặt trước.'], 404);
        }

        // Không cho phép thay đổi đơn đã hoàn thành hoặc đã hủy
        if (in_array($dht->TRANG_THAI, ['da_giao', 'da_huy'])) {
            return response()->json(['message' => 'Không thể thay đổi trạng thái đơn đã hoàn thành hoặc đã hủy.'], 400);
        }

        $dht->TRANG_THAI = $request->trang_thai;
        $dht->save();

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công.',
            'data'    => $this->formatOrder($dht->load(['cuaHang', 'chiTiets'])),
        ]);
    }

    // =========================================================
    // POST /admin/pre-orders/{id}/complete-payment — Thu tiền còn lại
    // =========================================================
    public function completePayment(Request $request, $id)
    {
        $request->validate([
            'phuong_thuc_thanh_toan' => 'required|string|max:50',
        ]);

        $dht = DatHangTruoc::with('chiTiets')->find($id);
        if (!$dht) {
            return response()->json(['message' => 'Không tìm thấy đơn đặt trước.'], 404);
        }

        if (!in_array($dht->TRANG_THAI, ['cho_xac_nhan', 'da_coc', 'san_sang'])) {
            return response()->json([
                'message' => 'Đơn hàng ở trạng thái không hợp lệ để hoàn thành thanh toán.',
            ], 400);
        }

        $storeId = $dht->MACUAHANG;
        $user    = Auth::guard('api')->user();

        // Sinh mã HdBan (tối đa 10 ký tự theo schema hdban.MAHD)
        $mahd = 'HD' . substr(time(), -6) . rand(10, 99);

        DB::beginTransaction();
        try {
            // Tạo hóa đơn bán hàng chính thức
            HdBan::create([
                'MAHD'                  => $mahd,
                'NGAYLAP'               => now(),
                'TONGTIEN'              => $dht->TONG_TIEN,
                'PHUONGTHUCTHANHTOAN'   => $request->phuong_thuc_thanh_toan,
                'TRANGTHAITHANHTOAN'    => 'Đã hoàn thành',
                'GHICHU'                => 'Đặt trước ' . $dht->MADHT . ' - ' . $dht->HOTEN_KH,
                'MAKH'                  => $dht->MAKH ?? 'KH000000',
                'MAVANDON'              => null,
                'MACUAHANG'             => $storeId,
            ]);

            // Inventory trigger variables (dùng POS_SALE để khớp ENUM type của inventory_movements)
            DB::statement('SET @inv_type = ?, @inv_ref_type = ?, @inv_ref_id = ?, @inv_username = ?', [
                'POS_SALE', 'DatHangTruoc', $dht->MADHT, $user->USERNAME ?? null,
            ]);

            // Tạo chi tiết hóa đơn + trừ kho
            foreach ($dht->chiTiets as $ct) {
                ChiTietHdBan::create([
                    'MAHD'    => $mahd,
                    'MASP'    => $ct->MASP,
                    'SOLUONG' => $ct->SO_LUONG,
                    'DONGIA'  => $ct->DON_GIA,
                ]);

                if ($ct->MASP) {
                    // Trừ tonkho_cuahang
                    $tonKho = TonKhoCuaHang::where('MACUAHANG', $storeId)
                                            ->where('MASP', $ct->MASP)->first();
                    if ($tonKho) {
                        DB::table('tonkho_cuahang')
                            ->where('MACUAHANG', $storeId)
                            ->where('MASP', $ct->MASP)
                            ->update(['SOLUONG_TON' => max(0, $tonKho->SOLUONG_TON - $ct->SO_LUONG)]);
                    }

                    // Trừ bảng sanpham
                    $sp = SanPham::find($ct->MASP);
                    if ($sp) {
                        $sp->SOLUONG = max(0, $sp->SOLUONG - $ct->SO_LUONG);
                        $sp->save();
                    }
                }
            }

            // Tích điểm cho khách hàng (nếu có tài khoản)
            if ($dht->MAKH && $dht->MAKH !== 'KH000000') {
                $kh = KhachHang::find($dht->MAKH);
                if ($kh) {
                    $kh->DIEMTICHLUY += floor($dht->TONG_TIEN / 10000);
                    $kh->save();
                }
            }

            // Đánh dấu đơn đặt trước là đã giao
            $dht->TRANG_THAI       = 'da_giao';
            $dht->MAHD_HOAN_THANH  = $mahd;
            $dht->save();

            DB::commit();

            // Xóa cache sản phẩm
            Cache::tags(['products'])->flush();

            return response()->json([
                'message' => 'Thanh toán hoàn tất. Hóa đơn đã được tạo.',
                'mahd'    => $mahd,
                'madht'   => $dht->MADHT,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi hoàn thành thanh toán: ' . $e->getMessage()], 500);
        }
    }
}
