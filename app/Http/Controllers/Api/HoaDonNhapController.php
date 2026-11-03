<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietHoaDonNhap;
use App\Models\ChuKySo;
use App\Models\HoaDonNhap;
use App\Models\LoHang;
use App\Models\NguoiDung;
use App\Models\NhaCungCap;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use App\Models\YeuCauDieuChuyen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoaDonNhapController extends Controller
{
    /**
     * Danh sách hóa đơn nhập hàng (lô hàng)
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();
        $profile = $user ? (NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung) : null;

        $query = HoaDonNhap::with([
            'nhaCungCap',
            'cuaHang',
            'nguoiNhap',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang'
        ])->latest();

        // Nhân sự thuộc chi nhánh chỉ xem hóa đơn nhập của chi nhánh mình
        if ($profile?->cuaHangId && in_array((int) ($user?->vaiTroId ?? 0), [2, 3, 4, 7])) {
            $query->where('cuaHangId', $profile->cuaHangId);
        } elseif ($request->filled('cuaHangId')) {
            $query->where('cuaHangId', $request->input('cuaHangId'));
        }

        if ($request->filled('trangThai')) {
            $query->where('trangThai', $request->input('trangThai'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('maHoaDonNhap', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        }

        $invoices = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách hóa đơn nhập hàng thành công',
            'data' => $invoices,
        ]);
    }

    /**
     * Quản lý chi nhánh tự tạo đơn nhập hàng mới cho chi nhánh của họ.
     * - Role Giám đốc (vaiTroId = 8) chỉ duyệt các đơn yêu cầu nhập hàng khi cần thiết, KHÔNG trực tiếp tạo đơn nhập hàng mới.
     * - Role Admin (vaiTroId = 1) KHÔNG tham gia tạo hoặc ký duyệt đơn nhập hàng.
     * - Nếu Quản lý chi nhánh đã ký duyệt đơn nhập hàng thì đơn hoàn tất luôn, không cần các role khác ký nữa.
     * - Ngược lại nếu chưa có phê duyệt của Quản lý chi nhánh thì đơn ở trạng thái chờ ký (cần chữ ký của Nhân viên kho và Nhân viên kế toán, hoặc Quản lý chi nhánh ký sau).
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập!',
            ], 401);
        }

        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));

        if ($vaiTroId === 8 || $maRole === 5) {
            return response()->json([
                'success' => false,
                'message' => 'Ban Giám Đốc chỉ duyệt các đơn yêu cầu nhập hàng khi cần thiết, không trực tiếp tham gia tạo đơn nhập hàng mới. Việc tạo đơn nhập hàng mới do Quản lý chi nhánh thực hiện cho chi nhánh của mình!',
            ], 403);
        }

        if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Quản trị viên (Admin) không được phép tạo hoặc tham gia ký duyệt đơn nhập hàng! Việc tạo đơn nhập hàng mới do Quản lý chi nhánh thực hiện.',
            ], 403);
        }

        if ($vaiTroId !== 2 && $maRole !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Quản lý chi nhánh mới có quyền tạo đơn nhập hàng mới cho chi nhánh của mình!',
            ], 403);
        }

        $profile = NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $userId = $profile?->id ?? $user->id;
        $branchId = $profile?->cuaHangId ?? $request->input('cuaHangId') ?? $request->input('cua_hang_id') ?? $request->input('MACUAHANG_NHAP') ?? $request->input('MACUAHANG') ?? 1;

        // Nếu quản lý có gán chi nhánh cố định thì luôn tự động áp dụng chi nhánh đó
        if ($profile?->cuaHangId) {
            $branchId = (int) $profile->cuaHangId;
        }

        $request->merge([
            'nhaCungCapId' => $request->input('nhaCungCapId') ?? $request->input('nha_cung_cap_id') ?? $request->input('MANCC'),
            'cuaHangId' => $branchId,
            'nguoi_nhap_id' => $userId,
            'ghiChu' => $request->input('ghiChu') ?? $request->input('ghi_chu') ?? $request->input('GHICHU'),
        ]);

        $rawItems = $request->input('items') ?? $request->input('chi_tiet') ?? [];
        $items = array_map(function ($item) {
            $spId = $item['sanPhamId'] ?? $item['san_pham_id'] ?? $item['sanpham_id'] ?? $item['MASP'] ?? null;
            if ($spId && !is_numeric($spId)) {
                $foundSp = \App\Models\SanPham::where('maSanPham', $spId)->orWhere('MASP', $spId)->orWhere('slug', $spId)->first();
                if ($foundSp) {
                    $spId = $foundSp->id;
                }
            } elseif ($spId) {
                $spId = (int) $spId;
            }

            return [
                'sanPhamId' => $spId,
                'soLuong' => (int) ($item['soLuong'] ?? $item['so_luong'] ?? $item['SOLUONGTCT'] ?? $item['SOLUONGTN'] ?? $item['SOLUONG'] ?? 1),
                'don_gia_nhap' => (float) ($item['don_gia_nhap'] ?? $item['donGiaNhap'] ?? $item['DONGIANHAP'] ?? 0),
                'ngaySanXuat' => $item['ngaySanXuat'] ?? $item['ngay_san_xuat'] ?? $item['NGAYSX'] ?? $item['NGAYSANXUAT'] ?? null,
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
            'items.*.sanPhamId' => 'required|exists:sanPham,id',
            'items.*.soLuong' => 'required|integer|min:1',
            'items.*.don_gia_nhap' => 'required|numeric|min:0',
            'items.*.ngaySanXuat' => 'nullable|date',
            'items.*.hsd' => 'required|date',
        ]);

        // Nếu Quản lý chi nhánh ký duyệt ngay (truyền chữ ký hoặc chọn ký duyệt ngay):
        // -> Quản lý chi nhánh (vaiTroKy = 3) được ghi nhận đã ký, đơn nhập hoàn tất ngay và không cần các role khác ký nữa!
        // Ngược lại nếu không ký ngay -> đơn ở trạng thái chờ ký (cần chữ ký của Nhân viên kho & Nhân viên kế toán, hoặc Quản lý chi nhánh ký sau).
        $kyQuanLy = $request->input('ky_quan_ly') ?? $request->input('ky_giam_doc') ?? null;
        $signImmediately = $request->boolean('auto_sign_all', false) || !empty($kyQuanLy);
        $yeuCauId = $request->input('yeuCauId') ?? $request->input('yeu_cau_id');

        try {
            DB::statement('CALL sp_create_hoadon_nhap(?, ?, ?, ?, ?, ?, ?, @hoadon_id, @ma_hoadon, @error)', [
                $validated['nhaCungCapId'],
                $validated['cuaHangId'],
                $validated['nguoi_nhap_id'],
                $validated['ghiChu'] ?? null,
                $signImmediately ? 1 : 0,
                $kyQuanLy,
                json_encode($validated['items']),
            ]);

            $res = DB::select('SELECT @hoadon_id AS hoadon_id, @ma_hoadon AS ma_hoadon, @error AS error')[0];

            if ($res->error) {
                return response()->json(['success' => false, 'message' => $res->error], 500);
            }

            $invoice = HoaDonNhap::find($res->hoadon_id);

            // Invalidate product & stock cache immediately
            \App\Http\Controllers\Api\ProductController::clearProductCache();
            \Illuminate\Support\Facades\Cache::flush();

            // Nếu được tạo từ Yêu Cầu Nhập Hàng của chi nhánh, cập nhật trạng thái yêu cầu
            if ($yeuCauId) {
                $yeuCau = YeuCauDieuChuyen::find($yeuCauId);
                if ($yeuCau && $yeuCau->trangThai == 0) {
                    $yeuCau->update([
                        'trangThai' => 1,
                        'nguoiDuyetId' => $validated['nguoi_nhap_id']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => $signImmediately
                    ? "Quản lý chi nhánh đã tạo và ký duyệt đơn nhập hàng {$invoice->maHoaDonNhap} thành công! Không cần các vai trò khác ký thêm, tồn kho chi nhánh đã được cập nhật."
                    : "Đã tạo đơn nhập hàng {$invoice->maHoaDonNhap} cho chi nhánh! Đơn đang chờ Quản lý chi nhánh ký duyệt, hoặc cần đủ chữ ký của Nhân viên kho và Nhân viên kế toán.",
                'data' => $invoice->load(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi khởi tạo hóa đơn nhập: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ban Giám Đốc duyệt đơn yêu cầu nhập hàng của chi nhánh khi cần thiết
     * (Không trực tiếp tạo đơn nhập hàng mới)
     */
    public function duyetYeuCauNhapHang(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        if ($vaiTroId !== 8 && $maRole !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Ban Giám Đốc mới có quyền phê duyệt yêu cầu nhập hàng của chi nhánh!',
            ], 403);
        }

        $yeuCau = YeuCauDieuChuyen::find($id);
        if (!$yeuCau) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy yêu cầu nhập hàng #' . $id], 404);
        }

        $profile = NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $yeuCau->update([
            'trangThai' => 1,
            'nguoiDuyetId' => $profile?->id ?? $user->id,
        ]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Ban Giám Đốc đã phê duyệt Yêu cầu nhập hàng #{$yeuCau->id}! Quản lý chi nhánh có thể tiến hành tạo đơn nhập hàng cho chi nhánh.",
            'data' => $yeuCau,
        ]);
    }

    /**
     * Xem chi tiết hóa đơn nhập (hỗ trợ tìm bằng ID số hoặc Mã hóa đơn nhập HDN-...)
     */
    public function show($id)
    {
        $invoice = HoaDonNhap::with([
            'nhaCungCap',
            'cuaHang',
            'nguoiNhap',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang'
        ])
        ->where('id', $id)
        ->orWhere('maHoaDonNhap', $id)
        ->first();

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn nhập hàng phù hợp với mã: ' . $id,
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết hóa đơn nhập hàng',
            'data' => $invoice,
        ]);
    }

    /**
     * Chi nhánh xác nhận kiểm đếm / ký duyệt nhập kho
     */
    public function xacNhanNhapKho(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));

        if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Quản trị viên (Admin) không được phép tham gia vào việc ký duyệt đơn nhập hàng!',
            ], 403);
        }

        $invoice = HoaDonNhap::with(['chiTiets.loHang', 'chiTiets.sanPham', 'cuaHang', 'chuKySos'])
            ->where('id', $id)
            ->orWhere('maHoaDonNhap', $id)
            ->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        if ((int) $invoice->trangThai === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn nhập hàng này đã được ký duyệt và nhập kho trước đó!'
            ], 400);
        }

        $slotRole = SignatureController::mapUserRoleToSlotRole($user);
        if (!$slotRole) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Quản lý chi nhánh, Nhân viên kho hoặc Nhân viên kế toán mới được ký duyệt đơn nhập hàng!'
            ], 403);
        }

        $profile = NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $items = $request->input('items', []);
        $kyNguoiNhan = $request->input('ky_nguoi_nhan') ?? $request->input('ky_quan_ly') ?? $profile?->chuKySo;
        $tenNguoiNhan = $request->input('ten_nguoi_nhan') ?: ($profile?->hoTen ?? $user->username);
        $ghiChuKiemDem = $request->input('ghi_chu', $request->input('ghiChu', 'Đã kiểm đếm và ký xác nhận'));

        DB::beginTransaction();
        try {
            // Cập nhật số lượng thực nhận trên lô hàng nếu có điều chỉnh khi kiểm đếm
            foreach ($items as $it) {
                $loHangId = $it['loHangId'] ?? $it['lo_hang_id'] ?? null;
                $slThucNhan = isset($it['soLuongThucNhan']) ? (int) $it['soLuongThucNhan'] : null;
                if ($loHangId && $slThucNhan !== null && $slThucNhan >= 0) {
                    $lo = LoHang::find($loHangId);
                    if ($lo) {
                        $lo->update(['soLuongCon' => $slThucNhan]);
                    }
                }
            }

            $slot = ChuKySo::firstOrCreate(
                ['loaiTaiLieu' => 1, 'taiLieuId' => $invoice->id, 'vaiTroKy' => $slotRole],
                [
                    'maChuKy' => 'SIG-HDN-' . $invoice->id . '-R' . $slotRole . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'tenNguoiKy' => $tenNguoiNhan,
                    'trangThai' => 0,
                ]
            );

            DB::statement('CALL sp_sign_role(?, ?, ?, ?, ?, @is_fully_signed, @error)', [
                $slot->id,
                $profile?->id ?? $user->id,
                $kyNguoiNhan ?: json_encode(['docId' => $invoice->id, 'signer' => $tenNguoiNhan]),
                'BRANCH_SIGNATURE',
                $ghiChuKiemDem,
            ]);

            $res = DB::select('SELECT @is_fully_signed AS is_fully_signed, @error AS error')[0];
            if ($res->error) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $res->error], 400);
            }

            $slot->update(['tenNguoiKy' => $tenNguoiNhan]);

            DB::commit();
            $isFullySigned = (bool) $res->is_fully_signed;

            // Invalidate product & stock cache immediately
            \App\Http\Controllers\Api\ProductController::clearProductCache();
            \Illuminate\Support\Facades\Cache::flush();

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => $isFullySigned
                    ? "Đã ký duyệt hoàn tất và cập nhật tồn kho thành công cho đơn nhập hàng {$invoice->maHoaDonNhap}!"
                    : "Đã ghi nhận chữ ký của bạn cho đơn nhập hàng {$invoice->maHoaDonNhap}! Đang chờ bên còn lại ký hoàn tất.",
                'data' => $invoice->fresh(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'chuKySos', 'chiTiets.sanPham', 'chiTiets.loHang']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xác nhận nhập kho: ' . $e->getMessage()
            ], 500);
        }
    }
}
