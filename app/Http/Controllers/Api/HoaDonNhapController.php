<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseOrderSignatureRole;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\ApprovePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\InspectPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\RejectPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\SignPurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\HoaDonNhap;
use App\Models\NguoiDung;
use App\Models\YeuCauDieuChuyen;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HoaDonNhapController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    private function findInvoiceOrFail($id): ?HoaDonNhap
    {
        return HoaDonNhap::with([
            'nhaCungCap',
            'cuaHang',
            'nguoiNhap',
            'nguoiDuyet',
            'signatures.nguoiKy',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang',
        ])
            ->where('id', $id)
            ->orWhere('maHoaDonNhap', $id)
            ->first();
    }

    /**
     * Danh sách hóa đơn nhập hàng
     */
    public function index(Request $request)
    {
        \App\Services\InventoryLedgerService::ensureSchema();
        $user = auth('api')->user();
        $profile = $user ? (NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung) : null;

        $query = HoaDonNhap::with([
            'nhaCungCap',
            'cuaHang',
            'nguoiNhap',
            'nguoiDuyet',
            'signatures.nguoiKy',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang',
        ])->latest();

        // Nhân sự thuộc chi nhánh chỉ xem hóa đơn nhập của chi nhánh mình
        if ($profile?->cuaHangId && in_array((int) ($user?->vaiTroId ?? 0), [2, 3, 4, 7], true)) {
            $query->where('cuaHangId', $profile->cuaHangId);
        } elseif ($request->filled('cuaHangId')) {
            $query->where('cuaHangId', $request->input('cuaHangId'));
        }

        if ($request->filled('maTrangThai')) {
            $statusParam = strtoupper((string) $request->input('maTrangThai'));
            $query->where(function ($q) use ($statusParam) {
                $q->where('maTrangThai', $statusParam);
                if ($statusParam === PurchaseOrderStatus::HOAN_TAT->value) {
                    $q->orWhere(function ($sub) {
                        $sub->whereNull('maTrangThai')->where('trangThai', 1);
                    });
                } elseif ($statusParam === PurchaseOrderStatus::CHO_DUYET->value) {
                    $sub->orWhere(function ($sub) {
                        $sub->whereNull('maTrangThai')->where('trangThai', 0);
                    });
                }
            });
        } elseif ($request->filled('trangThai')) {
            $query->where('trangThai', $request->input('trangThai'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('maHoaDonNhap', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        }

        $invoices = $query->paginate((int) $request->get('per_page', 15));
        $invoices->setCollection(
            $invoices->getCollection()->map(fn (HoaDonNhap $item) => (new PurchaseOrderResource($item))->resolve($request))
        );

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách hóa đơn nhập hàng thành công',
            'data' => $invoices,
        ]);
    }

    /**
     * Giai đoạn 1: Tạo phiếu nhập hàng mới (KHÔNG cộng tồn kho lúc tạo).
     * - Nhân viên kho tạo -> CHO_DUYET
     * - Quản lý chi nhánh tạo -> DA_DUYET
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Bạn chưa đăng nhập!'], 401);
        }

        $authCheck = Gate::forUser($user)->inspect('create', HoaDonNhap::class);
        if ($authCheck->denied()) {
            return response()->json([
                'success' => false,
                'message' => $authCheck->message() ?: 'Bạn không có quyền tạo phiếu nhập hàng.',
            ], 403);
        }

        $profile = NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $userId = $profile?->id ?? $user->id;
        $branchId = $profile?->cuaHangId
            ?? $request->input('cuaHangId')
            ?? $request->input('cua_hang_id')
            ?? $request->input('MACUAHANG_NHAP')
            ?? $request->input('MACUAHANG')
            ?? 1;

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
            'items' => 'required|array|min:1',
            'items.*.sanPhamId' => 'required|exists:sanPham,id',
            'items.*.soLuong' => 'required|integer|min:1',
            'items.*.don_gia_nhap' => 'required|numeric|min:0',
            'items.*.ngaySanXuat' => 'nullable|date',
            'items.*.hsd' => 'required|date',
        ]);

        $yeuCauId = $request->input('yeuCauId') ?? $request->input('yeu_cau_id');
        $invoice = $this->purchaseOrderService->create($user, $validated, $yeuCauId ? (int) $yeuCauId : null);
        $status = $invoice->resolvedStatus();

        $msg = $status === PurchaseOrderStatus::DA_DUYET
            ? "Quản lý chi nhánh đã tạo phiếu nhập hàng {$invoice->maHoaDonNhap} (trạng thái: Đã duyệt). Vui lòng kiểm kê và ký đủ chữ ký khi nhận hàng để cộng tồn kho."
            : "Nhân viên kho đã tạo phiếu nhập hàng {$invoice->maHoaDonNhap} (trạng thái: Chờ duyệt). Đang chờ Quản lý chi nhánh phê duyệt.";

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $msg,
            'data' => new PurchaseOrderResource($invoice),
        ], 201);
    }

    /**
     * Quản lý chi nhánh duyệt phiếu nhập hàng của Nhân viên kho: CHO_DUYET -> DA_DUYET
     */
    public function approve(ApprovePurchaseOrderRequest $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $invoice = $this->findInvoiceOrFail($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        $authCheck = Gate::forUser($user)->inspect('approve', $invoice);
        if ($authCheck->denied()) {
            return response()->json(['success' => false, 'message' => $authCheck->message()], 403);
        }

        $updated = $this->purchaseOrderService->approve($invoice, $user, $request->validated('ghiChu'));

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Đã duyệt phiếu nhập hàng {$updated->maHoaDonNhap}! Khi nhà cung cấp giao hàng, chi nhánh có thể tiến hành kiểm kê và ký nhận.",
            'data' => new PurchaseOrderResource($updated),
        ]);
    }

    /**
     * Quản lý chi nhánh từ chối phiếu nhập hàng của Nhân viên kho: CHO_DUYET -> TU_CHOI
     */
    public function reject(RejectPurchaseOrderRequest $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $invoice = $this->findInvoiceOrFail($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        $authCheck = Gate::forUser($user)->inspect('reject', $invoice);
        if ($authCheck->denied()) {
            return response()->json(['success' => false, 'message' => $authCheck->message()], 403);
        }

        $updated = $this->purchaseOrderService->reject($invoice, $user, $request->validated('lyDoTuChoi'));

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Đã từ chối phiếu nhập hàng {$updated->maHoaDonNhap}.",
            'data' => new PurchaseOrderResource($updated),
        ]);
    }

    /**
     * Giai đoạn 2.1: Cập nhật số lượng thực nhận & ghi chú chênh lệch khi kiểm kê
     */
    public function inspect(InspectPurchaseOrderRequest $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $invoice = $this->findInvoiceOrFail($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        $authCheck = Gate::forUser($user)->inspect('inspect', $invoice);
        if ($authCheck->denied()) {
            return response()->json(['success' => false, 'message' => $authCheck->message()], 403);
        }

        $updated = $this->purchaseOrderService->updateInspection(
            $invoice,
            $request->validated('items'),
            $request->validated('ghiChu')
        );

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Đã lưu kết quả kiểm kê cho phiếu {$updated->maHoaDonNhap}! Tiếp tục ký đủ chữ ký theo quy định để hoàn tất nhập kho.",
            'data' => new PurchaseOrderResource($updated),
        ]);
    }

    /**
     * Giai đoạn 2.2: Ký xác nhận theo vai trò
     */
    public function sign(SignPurchaseOrderRequest $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $invoice = $this->findInvoiceOrFail($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        $roleEnum = PurchaseOrderSignatureRole::from($request->validated('vaiTroKy'));
        $authCheck = Gate::forUser($user)->inspect('sign', [$invoice, $roleEnum]);
        if ($authCheck->denied()) {
            return response()->json(['success' => false, 'message' => $authCheck->message()], 403);
        }

        $result = $this->purchaseOrderService->sign($invoice, $user, $roleEnum, [
            'tenNguoiKy' => $request->validated('tenNguoiKy'),
            'chuKyAnh' => $request->validated('chuKyAnh'),
            'ghiChu' => $request->validated('ghiChu'),
        ]);

        /** @var HoaDonNhap $updated */
        $updated = $result['invoice'];
        $msg = $result['completedNow']
            ? "Đã đủ chữ ký xác nhận! Phiếu {$updated->maHoaDonNhap} hoàn tất và đã cộng tồn kho theo số lượng thực nhận."
            : "Đã ghi nhận chữ ký của {$roleEnum->label()} cho phiếu {$updated->maHoaDonNhap}.";

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $msg,
            'data' => new PurchaseOrderResource($updated),
        ]);
    }

    /**
     * Ban Giám Đốc duyệt đơn yêu cầu điều chuyển/nhập hàng của chi nhánh (giữ tương thích ngược)
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
            'message' => "Ban Giám Đốc đã phê duyệt Yêu cầu nhập hàng #{$yeuCau->id}!",
            'data' => $yeuCau,
        ]);
    }

    /**
     * Xem chi tiết hóa đơn nhập
     */
    public function show($id)
    {
        $invoice = $this->findInvoiceOrFail($id);

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
            'data' => new PurchaseOrderResource($invoice),
        ]);
    }

    /**
     * Endpoint tương thích ngược: kiểm kê + ký xác nhận phần vai trò của user hiện tại
     */
    public function xacNhanNhapKho(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $invoice = $this->findInvoiceOrFail($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy phiếu nhập hàng ' . $id], 404);
        }

        $requestedRole = $request->input('vaiTroKy') ?? $request->input('vai_tro_ky');
        $roleEnum = $requestedRole
            ? PurchaseOrderSignatureRole::tryFrom((string) $requestedRole)
            : PurchaseOrderSignatureRole::fromUserRoleId((int) ($user->vaiTroId ?? 0));

        if (!$roleEnum) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Quản lý chi nhánh, Nhân viên kho hoặc Nhân viên kế toán mới được ký duyệt đơn nhập hàng!',
            ], 403);
        }

        $authCheck = Gate::forUser($user)->inspect('sign', [$invoice, $roleEnum]);
        if ($authCheck->denied()) {
            return response()->json(['success' => false, 'message' => $authCheck->message()], 403);
        }

        $result = $this->purchaseOrderService->sign($invoice, $user, $roleEnum, [
            'items' => $request->input('items', []),
            'tenNguoiKy' => $request->input('ten_nguoi_nhan') ?? $request->input('tenNguoiKy'),
            'chuKyAnh' => $request->input('ky_nguoi_nhan') ?? $request->input('ky_quan_ly') ?? $request->input('chuKyAnh'),
            'ghiChu' => $request->input('ghi_chu', $request->input('ghiChu', 'Đã kiểm đếm và ký xác nhận')),
        ]);

        $updated = $result['invoice'];
        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $result['completedNow']
                ? "Đã ký duyệt hoàn tất và cập nhật tồn kho thành công cho đơn nhập hàng {$updated->maHoaDonNhap}!"
                : "Đã ghi nhận chữ ký của {$roleEnum->label()} cho đơn nhập hàng {$updated->maHoaDonNhap}!",
            'data' => new PurchaseOrderResource($updated),
        ]);
    }
}
