<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderSignatureRole;
use App\Enums\PurchaseOrderStatus;
use App\Models\CuaHang;
use App\Models\HoaDonNhap;
use App\Models\NguoiDung;
use App\Models\NhaCungCap;
use App\Models\NhatKyKho;
use App\Models\SanPham;
use App\Models\TaiKhoan;
use App\Models\TonKhoChiNhanh;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private CuaHang $store1;
    private CuaHang $store2;
    private NhaCungCap $supplier;
    private SanPham $product;

    private TaiKhoan $warehouse1;
    private TaiKhoan $manager1;
    private TaiKhoan $accountant1;
    private TaiKhoan $manager2;
    private TaiKhoan $cashier1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store1 = CuaHang::first() ?? CuaHang::create([
            'tenCuaHang' => 'Givral Test CN1',
            'diaChiChiTiet' => '123 Test',
            'soDienThoai' => '0900000001',
            'trangThai' => 1,
        ]);

        $this->store2 = CuaHang::where('id', '!=', $this->store1->id)->first();
        if (!$this->store2) {
            $this->store2 = $this->store1->replicate();
            $this->store2->tenCuaHang = 'Givral Test CN2';
            $this->store2->soDienThoai = '0900000002';
            $this->store2->save();
        }

        $this->supplier = NhaCungCap::first() ?? NhaCungCap::create([
            'tenNcc' => 'NCC Test Givral',
            'diaChi' => 'HCM',
            'soDienThoai' => '0900000099',
            'trangThai' => 1,
        ]);

        $this->product = SanPham::first() ?? SanPham::create([
            'loaiId' => 1,
            'tenSanPham' => 'Bánh Test Workflow',
            'slug' => 'banh-test-workflow-' . uniqid(),
            'giaBan' => 50000,
            'donViTinh' => 'Cái',
            'trangThai' => 1,
        ]);

        $this->warehouse1 = $this->makeStaff('test_wh_1_' . uniqid(), 4, $this->store1->id, 'Thủ Kho CN1');
        $this->manager1 = $this->makeStaff('test_mgr_1_' . uniqid(), 2, $this->store1->id, 'Quản Lý CN1');
        $this->accountant1 = $this->makeStaff('test_acc_1_' . uniqid(), 7, $this->store1->id, 'Kế Toán CN1');
        $this->manager2 = $this->makeStaff('test_mgr_2_' . uniqid(), 2, $this->store2->id, 'Quản Lý CN2');
        $this->cashier1 = $this->makeStaff('test_cash_1_' . uniqid(), 3, $this->store1->id, 'Thu Ngân CN1');

        TonKhoChiNhanh::updateOrCreate(
            ['cuaHangId' => $this->store1->id, 'sanPhamId' => $this->product->id],
            ['soLuongTon' => 10]
        );
    }

    private function makeStaff(string $username, int $vaiTroId, int $cuaHangId, string $hoTen): TaiKhoan
    {
        $tk = TaiKhoan::create([
            'username' => $username,
            'password' => '123456',
            'email' => $username . '@test.local',
            'vaiTroId' => $vaiTroId,
            'trangThai' => 1,
        ]);

        NguoiDung::create([
            'taiKhoanId' => $tk->id,
            'cuaHangId' => $cuaHangId,
            'hoTen' => $hoTen,
            'loaiNguoiDung' => 1,
        ]);

        return $tk;
    }

    private function createPayload(int $qty = 20): array
    {
        return [
            'nhaCungCapId' => $this->supplier->id,
            'cuaHangId' => $this->store1->id,
            'ghiChu' => 'Phiếu test quy trình mới',
            'items' => [
                [
                    'sanPhamId' => $this->product->id,
                    'soLuong' => $qty,
                    'don_gia_nhap' => 25000,
                    'ngaySanXuat' => now()->toDateString(),
                    'hsd' => now()->addDays(5)->toDateString(),
                ],
            ],
        ];
    }

    public function test_warehouse_creates_po_as_cho_duyet_without_adding_stock(): void
    {
        $res = $this->actingAs($this->warehouse1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(20));

        $res->assertStatus(201)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::CHO_DUYET->value)
            ->assertJsonPath('data.nguoiTaoRole', 'WAREHOUSE')
            ->assertJsonPath('data.tonKhoDaCong', false);

        $stock = TonKhoChiNhanh::where('cuaHangId', $this->store1->id)
            ->where('sanPhamId', $this->product->id)
            ->value('soLuongTon');
        $this->assertEquals(10, (int) $stock);
    }

    public function test_manager_creates_po_as_da_duyet_without_adding_stock(): void
    {
        $res = $this->actingAs($this->manager1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(25));

        $res->assertStatus(201)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DA_DUYET->value)
            ->assertJsonPath('data.nguoiTaoRole', 'MANAGER')
            ->assertJsonPath('data.tonKhoDaCong', false);

        $stock = TonKhoChiNhanh::where('cuaHangId', $this->store1->id)
            ->where('sanPhamId', $this->product->id)
            ->value('soLuongTon');
        $this->assertEquals(10, (int) $stock);
    }

    public function test_manager_can_approve_and_reject_warehouse_po(): void
    {
        $po1 = $this->actingAs($this->warehouse1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(15))
            ->json('data');

        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$po1['id']}/duyet")
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DA_DUYET->value);

        $po2 = $this->actingAs($this->warehouse1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(15))
            ->json('data');

        // Từ chối thiếu lý do -> 422
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$po2['id']}/tu-choi", [])
            ->assertStatus(422);

        // Từ chối có lý do -> TU_CHOI
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$po2['id']}/tu-choi", ['lyDoTuChoi' => 'Sai đơn giá NCC'])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::TU_CHOI->value)
            ->assertJsonPath('data.lyDoTuChoi', 'Sai đơn giá NCC');
    }

    public function test_warehouse_po_requires_3_signatures_and_adds_actual_received_stock(): void
    {
        $po = $this->actingAs($this->warehouse1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(20))
            ->json('data');

        $poId = $po['id'];
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/duyet")
            ->assertStatus(200);

        // Kiểm kê thực nhận 17 cái (đặt 20)
        $this->actingAs($this->warehouse1, 'api')
            ->putJson("/api/hoadon-nhap/{$poId}/kiem-ke", [
                'items' => [
                    [
                        'sanPhamId' => $this->product->id,
                        'soLuongThucNhan' => 17,
                        'ghiChuChenhLech' => 'Thiếu 3 cái do vận chuyển',
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DANG_KIEM_KE->value);

        // Chữ ký 1/3: Nhân viên vận chuyển
        $this->actingAs($this->warehouse1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::NHAN_VIEN_VAN_CHUYEN->value,
                'tenNguoiKy' => 'Tài xế A',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DANG_KIEM_KE->value);

        // Chữ ký 2/3: Nhân viên kế toán -> vẫn chưa hoàn tất, tồn kho chưa tăng
        $this->actingAs($this->accountant1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::NHAN_VIEN_KE_TOAN->value,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DANG_KIEM_KE->value);

        $stockMid = TonKhoChiNhanh::where('cuaHangId', $this->store1->id)
            ->where('sanPhamId', $this->product->id)
            ->value('soLuongTon');
        $this->assertEquals(10, (int) $stockMid);

        // Chữ ký 3/3: Nhân viên kho -> HOAN_TAT và cộng đúng 17 (10 + 17 = 27)
        $this->actingAs($this->warehouse1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::NHAN_VIEN_KHO->value,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::HOAN_TAT->value)
            ->assertJsonPath('data.tonKhoDaCong', true);

        $stockAfter = TonKhoChiNhanh::where('cuaHangId', $this->store1->id)
            ->where('sanPhamId', $this->product->id)
            ->value('soLuongTon');
        $this->assertEquals(27, (int) $stockAfter);

        $ledgerQty = NhatKyKho::where('maThamChieu', $po['maHoaDonNhap'])->sum('soLuongThayDoi');
        $this->assertEquals(17, (int) $ledgerQty);
    }

    public function test_manager_po_requires_2_signatures_and_is_idempotent(): void
    {
        $po = $this->actingAs($this->manager1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(12))
            ->json('data');

        $poId = $po['id'];

        $this->actingAs($this->manager1, 'api')
            ->putJson("/api/hoadon-nhap/{$poId}/kiem-ke", [
                'items' => [['sanPhamId' => $this->product->id, 'soLuongThucNhan' => 11]],
            ])
            ->assertStatus(200);

        // Ký 1/2: Quản lý chi nhánh
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::QUAN_LY_CHI_NHANH->value,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::DANG_KIEM_KE->value);

        // Ký 2/2: Nhân viên giao hàng -> HOAN_TAT, tồn kho 10 + 11 = 21
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::NHAN_VIEN_GIAO_HANG->value,
                'tenNguoiKy' => 'Shipper B',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', PurchaseOrderStatus::HOAN_TAT->value);

        // Gọi ký lặp lại -> bị chặn, tồn kho không cộng lần 2
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::QUAN_LY_CHI_NHANH->value,
            ])
            ->assertStatus(422);

        $stockAfter = TonKhoChiNhanh::where('cuaHangId', $this->store1->id)
            ->where('sanPhamId', $this->product->id)
            ->value('soLuongTon');
        $this->assertEquals(21, (int) $stockAfter);
    }

    public function test_wrong_role_or_wrong_branch_returns_403(): void
    {
        $po = $this->actingAs($this->warehouse1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(10))
            ->json('data');

        $poId = $po['id'];

        // Thu ngân tạo phiếu -> 403
        $this->actingAs($this->cashier1, 'api')
            ->postJson('/api/hoadon-nhap', $this->createPayload(10))
            ->assertStatus(403);

        // Quản lý chi nhánh 2 duyệt phiếu chi nhánh 1 -> 403
        $this->actingAs($this->manager2, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/duyet")
            ->assertStatus(403);

        // Thủ kho tự duyệt phiếu của mình -> 403
        $this->actingAs($this->warehouse1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/duyet")
            ->assertStatus(403);

        // Duyệt xong -> Thủ kho cố tình ký thay Kế toán -> 403
        $this->actingAs($this->manager1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/duyet")
            ->assertStatus(200);

        $this->actingAs($this->warehouse1, 'api')
            ->postJson("/api/hoadon-nhap/{$poId}/ky-nhan", [
                'vaiTroKy' => PurchaseOrderSignatureRole::NHAN_VIEN_KE_TOAN->value,
            ])
            ->assertStatus(403);
    }

    public function test_legacy_completed_po_still_displays_correctly(): void
    {
        $legacyId = DB::table('hoaDonNhap')->insertGetId([
            'maHoaDonNhap' => 'HDN-LEGACY-' . uniqid(),
            'nhaCungCapId' => $this->supplier->id,
            'cuaHangId' => $this->store1->id,
            'nguoiNhapId' => $this->manager1->nguoidung->id,
            'ngayNhap' => now(),
            'tongTien' => 500000,
            'trangThai' => 1,
            'maTrangThai' => 'HOAN_TAT',
            'tonKhoDaCong' => true,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $this->actingAs($this->manager1, 'api')
            ->getJson("/api/hoadon-nhap/{$legacyId}")
            ->assertStatus(200)
            ->assertJsonPath('data.maTrangThai', 'HOAN_TAT')
            ->assertJsonPath('data.trangThai', 1)
            ->assertJsonPath('data.signatureProgress.isComplete', true);
    }
}

