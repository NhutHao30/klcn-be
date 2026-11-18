<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderSignatureRole;
use App\Enums\PurchaseOrderStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Seeder dữ liệu demo Givral Bakery (01/09/2026 - 17/11/2026).
 *
 * Chạy:  php artisan db:seed --class=DemoDataSeeder
 *
 * - IDEMPOTENT: mọi bản ghi có khoá tự nhiên (username, mã phiếu, mã đơn, mã lô...) chỉ được tạo nếu chưa có.
 *   Kế hoạch dữ liệu được sinh bằng RNG cố định (RNG_SEED) và KHÔNG phụ thuộc DB nên chạy lại cho kết quả giống hệt.
 * - KHÔNG xoá / sửa dữ liệu có sẵn (chỉ cập nhật tồn kho + thống kê của chính các bản ghi demo).
 * - Chỉ chạy trên DB local (DB_HOST = 127.0.0.1/localhost/::1) và APP_ENV khác production.
 */
class DemoDataSeeder extends Seeder
{
    private const START = '2026-09-01';
    private const END = '2026-11-17';
    private const PASSWORD = '123456';
    private const RNG_SEED = 20260918;
    private const MAX_STOCK = 50;
    private const STORE_COUNT = 3;

    // ------------------------------------------------------------------
    // ENTRY POINT
    // ------------------------------------------------------------------
    public function run(): void
    {
        $this->guardLocalEnvironment();
        $this->preflight();

        $products = $this->productDefs();
        $plan = $this->buildPlan($products, count($this->customerDefs()), $this->addressPool());

        $this->persist($plan, $products);
        $this->report($products);
    }

    // ------------------------------------------------------------------
    // SAFETY
    // ------------------------------------------------------------------
    private function guardLocalEnvironment(): void
    {
        $conn = (string) config('database.default');
        $host = (string) config("database.connections.{$conn}.host");
        $db = (string) config("database.connections.{$conn}.database");

        $this->command?->warn("DB đang dùng: DB_HOST={$host} | DB_DATABASE={$db} | APP_ENV=" . app()->environment());

        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException("Từ chối chạy: DB_HOST='{$host}' không phải database local.");
        }
        if (app()->environment('production')) {
            throw new RuntimeException('Từ chối chạy: APP_ENV=production.');
        }
    }

    /** Kiểm tra schema (KHÔNG chạy DDL). Nếu thiếu cột/bảng -> báo lỗi rõ ràng. */
    private function preflight(): void
    {
        $required = [
            'taikhoan' => ['username', 'email', 'password', 'vaiTroId', 'trangThai', 'createdAt', 'updatedAt'],
            'nguoidung' => ['taiKhoanId', 'cuaHangId', 'hoTen', 'soDienThoai', 'gioiTinh', 'loaiNguoiDung', 'caLamViec', 'luong', 'diemTichLuy', 'tongChiTieu', 'hangThanhVien', 'createdAt', 'updatedAt'],
            'cuahang' => ['tenCuaHang', 'soDienThoai', 'diaChiChiTiet', 'maTinh', 'maHuyen', 'maXa', 'ghnShopId', 'trangThai', 'createdAt', 'updatedAt'],
            'loaiSanPham' => ['tenLoai', 'slug', 'trangThai', 'createdAt', 'updatedAt'],
            'sanPham' => ['loaiId', 'tenSanPham', 'slug', 'giaBan', 'hinhAnh', 'moTa', 'donViTinh', 'trangThai', 'createdAt', 'updatedAt'],
            'nhaCungCap' => ['tenNcc', 'soDienThoai', 'email', 'diaChi', 'trangThai', 'createdAt', 'updatedAt'],
            'tonKhoChiNhanh' => ['cuaHangId', 'sanPhamId', 'soLuongTon', 'createdAt', 'updatedAt'],
            'nhatKyKho' => ['cuaHangId', 'sanPhamId', 'loaiBienDong', 'soLuongThayDoi', 'soLuongTonSau', 'maThamChieu', 'createdAt', 'updatedAt'],
            'donHang' => ['maDonHang', 'nguoidungId', 'cuaHangId', 'loaiDonHang', 'tongTienHang', 'soTienGiam', 'phiVanChuyen', 'tongThanhToan', 'phuongThucThanhToan', 'trangThaiThanhToan', 'trangThaiDonHang', 'maVanDonGhn', 'ghnStatusCode', 'diaChiGiaoHang', 'createdAt', 'updatedAt'],
            'chiTietDonHang' => ['donHangId', 'sanPhamId', 'soLuong', 'donGia', 'thanhTien', 'createdAt', 'updatedAt'],
            'lichSuTrangThaiDonHang' => ['donHangId', 'trangThaiCu', 'trangThaiMoi', 'ghnStatusCode', 'moTaChiTiet', 'nguonCapNhat', 'createdAt', 'updatedAt'],
            'hoaDonNhap' => ['maHoaDonNhap', 'nhaCungCapId', 'cuaHangId', 'nguoiNhapId', 'ngayNhap', 'tongTien', 'ghiChu', 'trangThai', 'maTrangThai', 'nguoiTaoRole', 'nguoiDuyetId', 'thoiGianDuyet', 'lyDoTuChoi', 'tonKhoDaCong', 'createdAt', 'updatedAt'],
            'chiTietHoaDonNhap' => ['hoaDonNhapId', 'loHangId', 'sanPhamId', 'soLuong', 'soLuongThucNhan', 'ghiChuChenhLech', 'donGiaNhap', 'thanhTien'],
            'loHang' => ['maLoHang', 'sanPhamId', 'cuaHangId', 'nhaCungCapId', 'soLuongNhap', 'soLuongCon', 'ngaySanXuat', 'hsd', 'giaNhap', 'trangThai', 'createdAt', 'updatedAt'],
            'purchase_order_signatures' => ['hoaDonNhapId', 'vaiTroKy', 'nguoiKyId', 'tenNguoiKy', 'chuKyAnh', 'ghiChu', 'thoiGianKy', 'created_at', 'updated_at'],
            'posShifts' => ['cuaHangId', 'nhanVienId', 'thoiGianMoCa', 'thoiGianDongCa', 'tienMatDauCa', 'doanhThuTienMat', 'doanhThuChuyenKhoan', 'doanhThuMomo', 'tongDoanhThu', 'tienMatSach', 'tienMatThucTe', 'chenhLech', 'trangThai', 'createdAt', 'updatedAt'],
        ];

        $problems = [];
        foreach ($required as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $problems[] = "thiếu bảng `{$table}`";
                continue;
            }
            $missing = array_values(array_filter($columns, fn ($c) => !Schema::hasColumn($table, $c)));
            if ($missing) {
                $problems[] = "bảng `{$table}` thiếu cột: " . implode(', ', $missing);
            }
        }
        if (!Schema::hasTable('vaiTro') && !Schema::hasTable('vaitro')) {
            $problems[] = 'thiếu bảng vai trò (`vaiTro`/`vaitro`)';
        }

        if ($problems) {
            throw new RuntimeException(
                "Schema local chưa đủ để seed (seeder KHÔNG tự chạy DDL/migrate):\n - " . implode("\n - ", $problems)
                . "\nHãy tự chạy các migration còn thiếu rồi seed lại."
            );
        }
    }

    // ------------------------------------------------------------------
    // STATIC DEFINITIONS
    // ------------------------------------------------------------------
    private function holidayMap(): array
    {
        return [
            '2026-09-02' => 'Quốc khánh 2/9',
            '2026-09-25' => 'Tết Trung thu',
            '2026-10-20' => 'Ngày Phụ nữ Việt Nam 20/10',
            '2026-10-31' => 'Halloween 31/10',
            '2026-11-11' => 'Ngày hội mua sắm 11/11',
        ];
    }

    /** Chi nhánh theo thứ tự 1..3 (ordinal, KHÔNG phải id DB). */
    private function storeDefs(): array
    {
        return [
            1 => ['tenCuaHang' => 'Givral Nguyễn Trãi', 'soDienThoai' => '02838351234', 'diaChiChiTiet' => '282 Nguyễn Trãi, Phường 8, Quận 5, TP.HCM', 'ghnShopId' => 123456],
            2 => ['tenCuaHang' => 'Givral Bình Tân', 'soDienThoai' => '0353144481', 'diaChiChiTiet' => '171 Trần Thanh Mại, Quận Bình Tân, TP.HCM', 'ghnShopId' => 123457],
            3 => ['tenCuaHang' => 'Givral An Giang', 'soDienThoai' => '02963841234', 'diaChiChiTiet' => '25 Lê Lợi, TP. Long Xuyên, An Giang', 'ghnShopId' => 123458],
        ];
    }

    private function supplierDefs(): array
    {
        return [
            'A' => ['tenNcc' => 'Công ty TNHH Bột Mỳ & Nguyên Liệu Givral Food', 'soDienThoai' => '02838445566', 'email' => 'contact@givralfood.vn', 'diaChi' => '120 Trần Hưng Đạo, Quận 1, TP. Hồ Chí Minh'],
            'B' => ['tenNcc' => 'Công ty Cổ phần Sữa & Socola Puratos Grand-Place', 'soDienThoai' => '02743789101', 'email' => 'info@puratosgrandplace.com', 'diaChi' => 'KCN Việt Nam - Singapore, Bình Dương'],
        ];
    }

    private function categoryDefs(): array
    {
        return [
            1 => ['tenLoai' => 'Bánh Kem', 'slug' => 'banh-kem'],
            2 => ['tenLoai' => 'Bánh Ngọt', 'slug' => 'banh-ngot'],
            3 => ['tenLoai' => 'Bánh Mì', 'slug' => 'banh-mi'],
            4 => ['tenLoai' => 'Bánh Khô', 'slug' => 'banh-kho'],
            5 => ['tenLoai' => 'Bánh Tráng Miệng', 'slug' => 'banh-trang-mieng'],
            6 => ['tenLoai' => 'Bánh Theo Mùa', 'slug' => 'banh-theo-mua'],
        ];
    }

    /**
     * Danh mục sản phẩm demo (slug bắt đầu bằng "demo-" để tách biệt dữ liệu có sẵn).
     * importDays: thứ trong tuần (ISO 1=T2..7=CN) nhập hàng. Bánh tươi nhập 2-3 đợt/tuần (HSD >= khoảng cách giữa 2 đợt),
     * bánh khô nhập 1 đợt/tuần (HSD dài).
     */
    private function productDefs(): array
    {
        $fresh3 = [1, 3, 5]; // T2, T4, T6 (HSD 3 ngày)
        $fresh4 = [1, 4];    // T2, T5 (HSD 4-5 ngày)
        $dry = [1];          // T2 hàng tuần

        $rows = [
            // ── Trang 1: Bánh Khô (loaiId = 4, dry, NCC A) ───────────────────────
            ['demo-banh-sung-bo-mini', 'Bánh Sừng Bò Mini', 4, 36000, 'dry', 15, $dry, 4, 'A', 'productnew7.webp'],
            ['demo-banh-mi-nuong-pho-mai-que', 'Bánh Mì Nướng Phô Mai Que', 4, 15000, 'dry', 30, $dry, 7, 'A', 'productnew7.webp'],
            ['demo-banh-mi-nuong-caramen', 'Bánh Mì Nướng Caramen', 4, 15000, 'dry', 30, $dry, 7, 'A', 'productnew7.webp'],
            ['demo-banh-mi-nuong-bo-toi', 'Bánh Mì Nướng Bơ Tỏi', 4, 15000, 'dry', 30, $dry, 7, 'A', 'productnew7.webp'],
            ['demo-banh-quy-dua', 'Bánh Quy Dừa', 4, 42000, 'dry', 75, $dry, 4, 'A', 'productSale_8.webp'],
            ['demo-banh-quy-hanh-nhan', 'Bánh Quy Hạnh Nhân', 4, 42000, 'dry', 90, $dry, 4, 'A', 'productSale_10.webp'],
            ['demo-banh-quy-bo-mut-dau', 'Bánh Quy Bơ Mứt Dâu', 4, 42000, 'dry', 75, $dry, 4, 'A', 'productSale_8.webp'],
            ['demo-banh-lady-finger', 'Bánh Lady Finger', 4, 42000, 'dry', 60, $dry, 3, 'A', 'productSale_10.webp'],

            // ── Trang 1: Bánh Tráng Miệng (loaiId = 5, fresh, NCC B) ──────────────
            ['demo-banh-pana-cotta', 'Bánh Pana Cotta', 5, 22000, 'fresh', 4, $fresh4, 6, 'B', 'productSale_8.webp'],
            ['demo-sua-chua', 'Sữa Chua', 5, 13000, 'fresh', 5, $fresh4, 7, 'B', 'productSale_8.webp'],
            ['demo-caramen', 'Caramen', 5, 13000, 'fresh', 5, $fresh4, 7, 'B', 'productSale_8.webp'],
            ['demo-mousse-tra-xanh', 'Mousse Trà Xanh', 5, 31000, 'fresh', 4, $fresh4, 4, 'B', 'productMenu_8.webp'],
            ['demo-mousse-chanh-leo', 'Mousse Chanh Leo', 5, 31000, 'fresh', 4, $fresh4, 4, 'B', 'productMenu_8.webp'],
            ['demo-banh-red-velvet-90g', 'Bánh Red Velvet 90G', 5, 58000, 'fresh', 4, $fresh4, 2, 'B', 'productMenu_6.webp'],
            ['demo-banh-tiramisu-90g', 'Bánh Tiramisu 90G', 5, 36000, 'fresh', 4, $fresh4, 4, 'B', 'productMenu_6.webp'],
            ['demo-banh-opera-90g', 'Bánh Opera 90G', 5, 36000, 'fresh', 4, $fresh4, 4, 'B', 'productMenu_6.webp'],

            // ── Trang 1: Bánh Ngọt (loaiId = 2, fresh, NCC B) ─────────────────────
            ['demo-banh-su-kem-nhan-vani', 'Bánh Su Kem Nhân Vani', 2, 29000, 'fresh', 4, $fresh4, 5, 'B', 'productSale_8.webp'],
            ['demo-banh-su-kem-nhan-socola', 'Bánh Su Kem Nhân Socola', 2, 30000, 'fresh', 4, $fresh4, 5, 'B', 'productSale_8.webp'],
            ['demo-banh-muffin-socola-chip', 'Bánh Muffin Socola Chip', 2, 19000, 'fresh', 4, $fresh4, 6, 'B', 'productnew4.webp'],
            ['demo-banh-gato-socola-sua', 'Bánh Gato Socola Sữa', 2, 40000, 'fresh', 4, $fresh4, 4, 'B', 'productMenu_6.webp'],
            ['demo-banh-cuon-socola-mieng', 'Bánh Cuộn Socola Miếng', 2, 23000, 'fresh', 4, $fresh4, 6, 'B', 'productnew4.webp'],
            ['demo-banh-cuon-vani-110g', 'Bánh Cuộn Vani 110G', 2, 35000, 'fresh', 4, $fresh4, 4, 'B', 'productnew4.webp'],
            ['demo-banh-cuon-tra-xanh-110g', 'Bánh Cuộn Trà Xanh 110G', 2, 35000, 'fresh', 4, $fresh4, 4, 'B', 'productnew4.webp'],
            ['demo-banh-chiffon-vani', 'Bánh Chiffon Vani', 2, 32000, 'fresh', 4, $fresh4, 4, 'B', 'productnew4.webp'],

            // ── Trang Bánh sinh nhật: Bánh Kem (loaiId = 1, fresh, NCC B) ────────
            ['demo-banh-kem-macaron-delight', 'Bánh Kem Macaron Delight', 1, 380000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_8.webp'],
            ['demo-banh-kem-dreamy-lady', 'Bánh Kem Dreamy Lady', 1, 380000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_8.webp'],
            ['demo-banh-hoang-kim', 'Bánh Hoàng Kim', 1, 320000, 'fresh', 4, $fresh4, 1, 'B', 'productSale_10.webp'],
            ['demo-banh-mousse-chocolate', 'Bánh Mousse Chocolate', 1, 390000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_6.webp'],
            ['demo-banh-kem-sweet-heart-4', 'Bánh Kem Sweet Heart 4', 1, 150000, 'fresh', 4, $fresh4, 2, 'B', 'productMenu_8.webp'],
            ['demo-banh-kem-amazing-chocolate', 'Bánh Kem Amazing Chocolate', 1, 380000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_6.webp'],
            ['demo-banh-kem-endless-love', 'Bánh Kem Endless Love', 1, 380000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_8.webp'],
            ['demo-banh-kem-princess', 'Bánh Kem Princess', 1, 380000, 'fresh', 4, $fresh4, 1, 'B', 'productMenu_8.webp'],

            // ── Trang 2 (collections/all?page=2 & /banh-mi, không trùng) ──────────
            ['demo-banh-chiffon-tra-xanh', 'Bánh Chiffon Trà Xanh', 2, 36000, 'fresh', 4, $fresh4, 4, 'B', 'productnew4.webp'],
            ['demo-banh-chiffon-3-vi', 'Bánh Chiffon 3 Vị', 2, 22000, 'fresh', 4, $fresh4, 6, 'B', 'productnew4.webp'],
            ['demo-banh-mi-nhan-sen-sua-dua', 'Bánh Mì Nhân Sen Sữa Dừa', 3, 10000, 'fresh', 3, $fresh3, 7, 'A', 'productnew7.webp'],
            ['demo-banh-mi-hat-oc-cho-sot-kem', 'Bánh Mì Hạt Óc Chó Sốt Kem', 3, 30000, 'fresh', 3, $fresh3, 4, 'A', 'productnew7.webp'],
            ['demo-banh-donut-socola-dau-45g', 'Bánh Donut Socola Dâu 45G', 3, 15000, 'fresh', 3, $fresh3, 6, 'B', 'productSale_8.webp'],
            ['demo-banh-donut-socola-trang-hanh-nhan-45g', 'Bánh Donut Socola Trắng Hạnh Nhân 45G', 3, 20000, 'fresh', 3, $fresh3, 6, 'B', 'productSale_8.webp'],
            ['demo-banh-donut-socola-tra-xanh-45g', 'Bánh Donut Socola Trà Xanh 45G', 3, 15000, 'fresh', 3, $fresh3, 6, 'B', 'productSale_8.webp'],
            ['demo-banh-mi-xuc-xich-ruoc', 'Bánh Mì Xúc Xích Ruốc', 3, 15000, 'fresh', 3, $fresh3, 7, 'A', 'productnew7.webp'],
            ['demo-banh-mi-nhan-soi-ga-sot-teriyaki', 'Bánh Mì Nhân Sợi Gà Sốt Teriyaki', 3, 15000, 'fresh', 3, $fresh3, 7, 'A', 'productnew7.webp'],
            ['demo-banh-danish-nho', 'Bánh Danish Nho', 3, 20000, 'fresh', 3, $fresh3, 5, 'B', 'productnew7.webp'],
            ['demo-banh-croissant', 'Bánh Croissant', 3, 15000, 'fresh', 3, $fresh3, 7, 'B', 'productnew7.webp'],
        ];

        $products = [];
        foreach ($rows as $i => [$slug, $ten, $loaiId, $gia, $class, $shelf, $days, $weight, $supplier, $img]) {
            $ratio = 0.55 + (($i * 7) % 11) / 100; // 0.55 - 0.65
            $products[$i] = [
                'slug' => $slug,
                'tenSanPham' => $ten,
                'loaiId' => $loaiId,
                'giaBan' => $gia,
                'class' => $class,
                'shelf' => $shelf,
                'importDays' => $days,
                'weight' => $weight,
                'supplier' => $supplier,
                'hinhAnh' => $img,
                'giaNhap' => (int) round($gia * $ratio, -2),
            ];
        }
        return $products;
    }

    /** Nhân sự: 1 Giám đốc + 3 chi nhánh x 6 người = 19 tài khoản. */
    private function staffDefs(): array
    {
        $people = [
            1 => ['quanly' => ['Nguyễn Hoàng Long', 1], 'kho' => ['Phạm Văn Dũng', 1], 'ketoan' => ['Vũ Thị Cẩm Tú', 0], 'banhang_sang' => ['Trần Thị Mai Anh', 0], 'banhang_chieu' => ['Lê Quốc Bảo', 1], 'cskh' => ['Đặng Thu Thảo', 0]],
            2 => ['quanly' => ['Lê Minh Khang', 1], 'kho' => ['Võ Thành Đạt', 1], 'ketoan' => ['Đỗ Thị Phương Vy', 0], 'banhang_sang' => ['Nguyễn Ngọc Hân', 0], 'banhang_chieu' => ['Trần Hữu Phước', 1], 'cskh' => ['Huỳnh Gia Bảo', 1]],
            3 => ['quanly' => ['Phạm Hoàng Nam', 1], 'kho' => ['Trương Quốc Huy', 1], 'ketoan' => ['Trần Thanh Trúc', 0], 'banhang_sang' => ['Lê Thị Bích Ngọc', 0], 'banhang_chieu' => ['Nguyễn Minh Tuấn', 1], 'cskh' => ['Phan Bảo Châu', 0]],
        ];
        $roleSpecs = [
            'quanly' => [2, 'Hành chính', 15500000],
            'kho' => [4, 'Ca Sáng', 9500000],
            'ketoan' => [7, 'Hành chính', 12000000],
            'banhang_sang' => [3, 'Ca Sáng', 8500000],
            'banhang_chieu' => [3, 'Ca Chiều', 8500000],
            'cskh' => [5, 'Ca Sáng', 9000000],
        ];

        $defs = [[
            'username' => 'giamdoc', 'vaiTroId' => 8, 'hoTen' => 'Trần Văn Thành', 'gioiTinh' => 1,
            'store' => null, 'key' => 'giamdoc', 'ca' => 'Hành chính', 'luong' => 35000000,
        ]];
        foreach ($people as $ord => $list) {
            foreach ($list as $key => [$name, $gender]) {
                [$roleId, $ca, $luong] = $roleSpecs[$key];
                $defs[] = [
                    'username' => "cn{$ord}_{$key}", 'vaiTroId' => $roleId, 'hoTen' => $name, 'gioiTinh' => $gender,
                    'store' => $ord, 'key' => $key, 'ca' => $ca, 'luong' => $luong,
                ];
            }
        }
        return $defs;
    }

    /** ~36 khách hàng mẫu (tên sinh theo chỉ số, cố định). */
    private function customerDefs(): array
    {
        $last = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Huỳnh', 'Võ', 'Đặng', 'Bùi'];
        $mid = [['Văn', 1], ['Thị', 0], ['Minh', 1], ['Ngọc', 0], ['Quốc', 1], ['Thu', 0]];
        $first = ['An', 'Bình', 'Chi', 'Dũng', 'Giang', 'Hà', 'Hùng', 'Khoa', 'Lan', 'Nam', 'Oanh', 'Phúc', 'Quyên', 'Sơn', 'Trang', 'Vy'];

        $defs = [];
        for ($i = 0; $i < 36; $i++) {
            [$m, $gender] = $mid[($i * 3) % count($mid)];
            $defs[] = [
                'username' => sprintf('khach_%02d', $i + 1),
                'hoTen' => $last[$i % count($last)] . ' ' . $m . ' ' . $first[($i * 5) % count($first)],
                'gioiTinh' => $gender,
                'soDienThoai' => '09' . sprintf('%08d', 31000000 + $i * 1379),
            ];
        }
        return $defs;
    }

    private function addressPool(): array
    {
        return [
            '12 Nguyễn Huệ, Quận 1, TP.HCM', '45 Lê Văn Sỹ, Quận 3, TP.HCM', '88 Phan Xích Long, Phú Nhuận, TP.HCM',
            '23 Cộng Hòa, Tân Bình, TP.HCM', '150 Lý Thường Kiệt, Quận 10, TP.HCM', '9 Võ Văn Ngân, Thủ Đức, TP.HCM',
            '77 Kinh Dương Vương, Bình Tân, TP.HCM', '31 Trần Hưng Đạo, TP. Long Xuyên, An Giang', '64 Hùng Vương, TP. Long Xuyên, An Giang',
            '5 Nguyễn Văn Linh, Quận 7, TP.HCM',
        ];
    }

    // ------------------------------------------------------------------
    // PLAN (THUẦN BỘ NHỚ - KHÔNG ĐỤNG DB, RNG CỐ ĐỊNH)
    // ------------------------------------------------------------------
    private function weightedPick(array $weights): int
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return array_key_first($weights);
        }
        $r = mt_rand(1, $total);
        foreach ($weights as $i => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $i;
            }
        }
        return array_key_last($weights);
    }

    private function pickQty(array $product, bool $holiday): int
    {
        if ($product['loaiId'] === 1) {
            return 1;
        }
        $r = mt_rand(1, 100);
        $p3 = $holiday ? 10 : 5;
        $p2 = $holiday ? 35 : 25;
        if ($r <= $p3) {
            return 3;
        }
        return $r <= $p3 + $p2 ? 2 : 1;
    }

    private function buildPlan(array $products, int $customerCount, array $addresses): array
    {
        mt_srand(self::RNG_SEED);

        $start = Carbon::parse(self::START)->startOfDay();
        $end = Carbon::parse(self::END)->startOfDay();
        $holidays = $this->holidayMap();
        $weights = array_column($products, 'weight');
        $hourWeights = [3, 4, 6, 10, 12, 8, 5, 5, 6, 8, 10, 8, 5, 3]; // 08h..21h

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->copy();
        }
        $dateStrs = array_map(fn ($d) => $d->format('Y-m-d'), $days);

        // ---------- 1. ĐƠN HÀNG ----------
        $orders = [];
        $demand = []; // [store][p][date] => qty
        foreach ($days as $day) {
            $dateStr = $day->format('Y-m-d');
            $isHoliday = isset($holidays[$dateStr]);
            $isWeekend = $day->isWeekend();

            for ($s = 1; $s <= self::STORE_COUNT; $s++) {
                $base = mt_rand(3, 8);
                $mult = 1.0;
                if ($isHoliday) {
                    $mult = mt_rand(150, 250) / 100;
                } elseif ($isWeekend) {
                    $mult = mt_rand(110, 125) / 100;
                }
                $count = max(1, (int) round($base * $mult));

                $dayOrders = [];
                for ($i = 0; $i < $count; $i++) {
                    $hour = 8 + $this->weightedPick($hourWeights);
                    $minute = $hour === 8 ? mt_rand(30, 59) : ($hour === 21 ? mt_rand(0, 29) : mt_rand(0, 59));
                    $second = mt_rand(0, 59);
                    $isPos = mt_rand(1, 100) <= 70;

                    $nLines = mt_rand(1, 4);
                    $w = $weights;
                    $lines = [];
                    for ($l = 0; $l < $nLines; $l++) {
                        $pIdx = $this->weightedPick($w);
                        $w[$pIdx] = 0;
                        if ($products[$pIdx]['loaiId'] === 1) {
                            // Mỗi đơn tối đa 1 bánh kem để không vượt ~800.000đ
                            foreach ($products as $kIdx => $prodItem) {
                                if ($prodItem['loaiId'] === 1) {
                                    $w[$kIdx] = 0;
                                }
                            }
                        }
                        $lines[] = [$pIdx, $this->pickQty($products[$pIdx], $isHoliday)];
                    }

                    $r = mt_rand(1, 100);
                    if ($isPos) {
                        $pm = $r <= 60 ? 0 : ($r <= 85 ? 1 : 2);
                        $customer = mt_rand(1, 100) <= 30 ? mt_rand(0, $customerCount - 1) : null;
                        $ship = 0;
                        $addr = 'Mua trực tiếp tại quầy';
                    } else {
                        $pm = $r <= 50 ? 0 : ($r <= 80 ? 1 : 2);
                        $customer = mt_rand(0, $customerCount - 1);
                        $ship = [15000, 20000, 25000, 30000][mt_rand(0, 3)];
                        $addr = $addresses[mt_rand(0, count($addresses) - 1)];
                    }

                    $status = 5;
                    if (!$isPos && $dateStr === self::END) { // đơn online buổi tối ngày cuối: đang xử lý/giao
                        if ($hour >= 20) {
                            $status = 1;
                        } elseif ($hour >= 19) {
                            $status = 2;
                        } elseif ($hour >= 18) {
                            $status = 3;
                        } elseif ($hour >= 17) {
                            $status = 4;
                        }
                    }

                    $subtotal = 0;
                    foreach ($lines as [$pIdx, $qty]) {
                        $subtotal += $products[$pIdx]['giaBan'] * $qty;
                    }

                    $dayOrders[] = [
                        'store' => $s,
                        'date' => $dateStr,
                        'ts' => sprintf('%s %02d:%02d:%02d', $dateStr, $hour, $minute, $second),
                        'hour' => $hour,
                        'type' => $isPos ? 'POS' : 'ONLINE',
                        'pm' => $pm,
                        'lines' => $lines,
                        'customer' => $customer,
                        'ship' => $ship,
                        'addr' => $addr,
                        'status' => $status,
                        'paid' => $isPos ? 1 : (($pm === 0 && $status !== 5) ? 0 : 1),
                        'subtotal' => $subtotal,
                        'total' => $subtotal + $ship,
                        'holiday' => $isHoliday,
                    ];
                }

                usort($dayOrders, fn ($a, $b) => strcmp($a['ts'], $b['ts']));
                foreach ($dayOrders as $k => $o) {
                    $o['seq'] = $k + 1;
                    $o['code'] = sprintf('%s-%s-C%d-%03d', $o['type'] === 'POS' ? 'POS' : 'HD', str_replace('-', '', $dateStr), $s, $o['seq']);
                    foreach ($o['lines'] as [$pIdx, $qty]) {
                        $demand[$s][$pIdx][$dateStr] = ($demand[$s][$pIdx][$dateStr] ?? 0) + $qty;
                    }
                    $orders[] = $o;
                }
            }
        }

        // ---------- 2. KẾ HOẠCH NHẬP HÀNG (theo nhu cầu bán sắp tới) ----------
        $imports = [];
        for ($s = 1; $s <= self::STORE_COUNT; $s++) {
            foreach ($products as $pIdx => $prod) {
                $importDates = [];
                foreach ($days as $day) {
                    if ($day->equalTo($start) || in_array($day->dayOfWeekIso, $prod['importDays'], true)) {
                        $importDates[] = $day->format('Y-m-d');
                    }
                }

                $carry = 0;
                foreach ($importDates as $k => $dStr) {
                    $nextStr = $importDates[$k + 1] ?? '9999-12-31';
                    $isLast = !isset($importDates[$k + 1]);

                    $need = 0;
                    foreach ($dateStrs as $ds) {
                        if ($ds >= $dStr && $ds < $nextStr) {
                            $need += $demand[$s][$pIdx][$ds] ?? 0;
                        }
                    }

                    if ($prod['class'] === 'dry') {
                        $buffer = mt_rand(2, 6);
                    } else {
                        $buffer = $isLast ? mt_rand(1, 3) : 0; // bánh tươi: không tồn dư giữa các đợt (tránh hết hạn)
                    }
                    if ($need + $buffer > self::MAX_STOCK) {
                        $buffer = max(0, self::MAX_STOCK - $need);
                    }

                    $qty = max(0, $need + $buffer - $carry);
                    if ($carry + $qty > self::MAX_STOCK) {
                        throw new RuntimeException("Nhu cầu vượt trần tồn kho: CN{$s} SP#{$pIdx} {$dStr} need={$need} carry={$carry}");
                    }
                    $carry = $carry + $qty - $need;
                    if ($qty <= 0) {
                        continue;
                    }

                    $extra = mt_rand(1, 100) <= 10 ? mt_rand(1, 2) : 0; // đặt dư so với thực nhận (thiếu hàng khi giao)
                    $nsx = $prod['class'] === 'dry'
                        ? Carbon::parse($dStr)->subDays(mt_rand(2, 8))->format('Y-m-d')
                        : $dStr;
                    $hsd = Carbon::parse($nsx)->addDays($prod['shelf'])->format('Y-m-d');

                    $imports[] = [
                        'store' => $s,
                        'p' => $pIdx,
                        'date' => $dStr,
                        'qty' => $qty,
                        'ordered' => $qty + $extra,
                        'note' => $extra > 0 ? "Thiếu {$extra} cái so với phiếu đặt (hao hụt khi vận chuyển)" : null,
                        'nsx' => $nsx,
                        'hsd' => $hsd,
                        'lotKey' => "{$s}|{$pIdx}|{$dStr}",
                        'lotCode' => sprintf('LH-DEMO-%s-C%d-P%02d', str_replace('-', '', $dStr), $s, $pIdx + 1),
                        'supplier' => $prod['supplier'],
                    ];
                }
            }
        }

        // ---------- 3. PHIẾU NHẬP (gộp theo chi nhánh + ngày + nhà cung cấp) ----------
        usort($imports, fn ($a, $b) => [$a['store'], $a['date'], $a['supplier'], $a['p']] <=> [$b['store'], $b['date'], $b['supplier'], $b['p']]);
        $drivers = ['Nguyễn Văn Tài', 'Trần Quốc Việt', 'Lê Hữu Nghĩa', 'Phạm Minh Tâm', 'Võ Thanh Sơn', 'Đỗ Công Thành'];
        $phieu = [];
        foreach ($imports as $idx => $imp) {
            $code = sprintf('HDN-DEMO-%s-C%d-%s', str_replace('-', '', $imp['date']), $imp['store'], $imp['supplier']);
            if (!isset($phieu[$code])) {
                $byManager = mt_rand(1, 100) <= 50;
                $createdMin = 420 + mt_rand(0, 15); // 07:00 - 07:15
                $phieu[$code] = [
                    'code' => $code,
                    'store' => $imp['store'],
                    'date' => $imp['date'],
                    'supplier' => $imp['supplier'],
                    'creator' => $byManager ? 'MANAGER' : 'WAREHOUSE',
                    'createdAt' => Carbon::parse($imp['date'])->addMinutes($createdMin)->format('Y-m-d H:i:s'),
                    'approvedAt' => $byManager ? null : Carbon::parse($imp['date'])->addMinutes($createdMin + 20)->format('Y-m-d H:i:s'),
                    'driver' => $drivers[mt_rand(0, count($drivers) - 1)],
                    'lines' => [],
                ];
            }
            $ph = &$phieu[$code];
            $ph['completedAt'] = Carbon::parse($imp['date'])->addMinutes($ph['creator'] === 'MANAGER' ? 485 : 490)->format('Y-m-d H:i:s'); // 08:05 / 08:10
            $ph['lines'][] = $idx;
            unset($ph);
            $imports[$idx]['phieu'] = $code;
        }
        foreach ($imports as $idx => $imp) {
            $imports[$idx]['completedAt'] = $phieu[$imp['phieu']]['completedAt'];
        }

        // ---------- 4. MÔ PHỎNG KHO (FEFO) -> SỔ KHO, TỒN CUỐI, SỐ LƯỢNG CÒN CỦA LÔ ----------
        $eventsByPair = [];
        foreach ($imports as $imp) {
            $eventsByPair["{$imp['store']}|{$imp['p']}"][] = [
                'ts' => $imp['completedAt'], 'kind' => 0, 'qty' => $imp['qty'], 'ref' => $imp['phieu'], 'type' => 4,
                'lotKey' => $imp['lotKey'], 'hsd' => $imp['hsd'],
            ];
        }
        foreach ($orders as $o) {
            foreach ($o['lines'] as [$pIdx, $qty]) {
                $eventsByPair["{$o['store']}|{$pIdx}"][] = [
                    'ts' => $o['ts'], 'kind' => 1, 'qty' => $qty, 'ref' => $o['code'], 'type' => $o['type'] === 'POS' ? 0 : 1,
                ];
            }
        }

        $ledger = [];
        $lotRemaining = [];
        $stockFinal = [];
        for ($s = 1; $s <= self::STORE_COUNT; $s++) {
            foreach (array_keys($products) as $pIdx) {
                $key = "{$s}|{$pIdx}";
                $events = $eventsByPair[$key] ?? [];
                usort($events, fn ($a, $b) => [$a['ts'], $a['kind']] <=> [$b['ts'], $b['kind']]);

                $stock = 0;
                $lots = [];
                foreach ($events as $ev) {
                    if ($ev['kind'] === 0) {
                        $lots[] = ['key' => $ev['lotKey'], 'hsd' => $ev['hsd'], 'rem' => $ev['qty']];
                        $lotRemaining[$ev['lotKey']] = $ev['qty'];
                        $stock += $ev['qty'];
                        $delta = $ev['qty'];
                    } else {
                        if ($stock < $ev['qty']) {
                            throw new RuntimeException("Tồn kho âm: CN{$s} SP#{$pIdx} tại {$ev['ts']} (tồn {$stock}, bán {$ev['qty']})");
                        }
                        usort($lots, fn ($a, $b) => [$a['hsd'], $a['key']] <=> [$b['hsd'], $b['key']]);
                        $left = $ev['qty'];
                        foreach ($lots as &$lot) {
                            if ($left <= 0) {
                                break;
                            }
                            $take = min($lot['rem'], $left);
                            $lot['rem'] -= $take;
                            $left -= $take;
                            $lotRemaining[$lot['key']] = $lot['rem'];
                        }
                        unset($lot);
                        $stock -= $ev['qty'];
                        $delta = -$ev['qty'];
                    }
                    if ($stock > self::MAX_STOCK) {
                        throw new RuntimeException("Tồn kho vượt " . self::MAX_STOCK . ": CN{$s} SP#{$pIdx} tại {$ev['ts']} (tồn {$stock})");
                    }
                    $ledger[] = [
                        'store' => $s, 'p' => $pIdx, 'type' => $ev['type'], 'delta' => $delta,
                        'after' => $stock, 'ref' => $ev['ref'], 'ts' => $ev['ts'],
                    ];
                }
                $stockFinal[$key] = $stock;
            }
        }

        // ---------- 5. CA POS (nhân viên bán hàng ca sáng/chiều) ----------
        $shiftAgg = [];
        foreach ($orders as $o) {
            if ($o['type'] !== 'POS') {
                continue;
            }
            $shiftNo = $o['hour'] < 14 ? 0 : 1;
            $k = "{$o['store']}|{$o['date']}|{$shiftNo}";
            $shiftAgg[$k] ??= ['store' => $o['store'], 'date' => $o['date'], 'shift' => $shiftNo, 'cash' => 0, 'bank' => 0, 'momo' => 0];
            $field = [0 => 'cash', 1 => 'bank', 2 => 'momo'][$o['pm']];
            $shiftAgg[$k][$field] += $o['total'];
        }
        ksort($shiftAgg);
        $shifts = [];
        $morningCash = [];
        foreach ($shiftAgg as $k => $sh) {
            $isMorning = $sh['shift'] === 0;
            $opening = $isMorning ? 2000000 : ($morningCash["{$sh['store']}|{$sh['date']}"] ?? 2000000);
            $expectedCash = $opening + $sh['cash'];
            if ($isMorning) {
                $morningCash["{$sh['store']}|{$sh['date']}"] = $expectedCash;
            }
            $shifts[] = $sh + [
                'cashierKey' => $isMorning ? 'banhang_sang' : 'banhang_chieu',
                'open' => $sh['date'] . ($isMorning ? ' 06:00:00' : ' 14:00:00'),
                'close' => $sh['date'] . ($isMorning ? ' 14:00:00' : ' 22:00:00'),
                'opening' => $opening,
                'expectedCash' => $expectedCash,
                'total' => $sh['cash'] + $sh['bank'] + $sh['momo'],
            ];
        }

        // ---------- 6. PHIẾU NHẬP DEMO CHƯA HOÀN TẤT (không cộng tồn kho) ----------
        $pending = [];
        for ($s = 1; $s <= self::STORE_COUNT; $s++) {
            $pending[] = [
                'status' => PurchaseOrderStatus::CHO_DUYET, 'store' => $s, 'supplier' => 'B', 'creator' => 'WAREHOUSE',
                'date' => '2026-11-17', 'createdAt' => '2026-11-17 14:30:00', 'approvedAt' => null,
                'items' => [[16, 12], [17, 10], [8, 12]],
            ];
            $pending[] = [
                'status' => PurchaseOrderStatus::DA_DUYET, 'store' => $s, 'supplier' => 'A',
                'creator' => $s === 2 ? 'WAREHOUSE' : 'MANAGER',
                'date' => '2026-11-17', 'createdAt' => '2026-11-17 15:10:00',
                'approvedAt' => $s === 2 ? '2026-11-17 16:00:00' : '2026-11-17 15:10:00',
                'items' => [[1, 15], [2, 12], [34, 15]],
            ];
            $pending[] = [
                'status' => PurchaseOrderStatus::TU_CHOI, 'store' => $s, 'supplier' => 'B', 'creator' => 'WAREHOUSE',
                'date' => '2026-11-14', 'createdAt' => '2026-11-14 10:00:00', 'approvedAt' => '2026-11-14 11:00:00',
                'reason' => 'Số lượng đặt vượt nhu cầu bán trong tuần, vui lòng giảm số lượng và đặt lại.',
                'items' => [[24, 3], [14, 8], [15, 8]],
            ];
        }

        return [
            'orders' => $orders,
            'imports' => $imports,
            'phieu' => array_values($phieu),
            'ledger' => $ledger,
            'lotRemaining' => $lotRemaining,
            'stockFinal' => $stockFinal,
            'shifts' => $shifts,
            'pending' => $pending,
        ];
    }

    // ------------------------------------------------------------------
    // PERSIST (1 TRANSACTION, IDEMPOTENT)
    // ------------------------------------------------------------------
    private function persist(array $plan, array $products): void
    {
        DB::transaction(function () use ($plan, $products) {
            $this->ensureRoles();
            $storeIds = $this->ensureStores();
            $supplierIds = $this->ensureSuppliers();
            $categoryIds = $this->ensureCategories();
            $productIds = $this->ensureProducts($products, $categoryIds);
            $people = $this->ensureAccounts($storeIds);

            $stats = [];
            $stats['orders'] = $this->persistOrders($plan, $storeIds, $productIds, $people);
            $stats['ledger'] = $this->persistLedger($plan, $storeIds, $productIds);
            $this->persistStock($plan, $storeIds, $productIds);
            $stats['phieu'] = $this->persistCompletedPurchaseOrders($plan, $products, $storeIds, $supplierIds, $productIds, $people);
            $stats['pending'] = $this->persistPendingPurchaseOrders($plan, $products, $storeIds, $supplierIds, $productIds, $people);
            $stats['shifts'] = $this->persistShifts($plan, $storeIds, $people);
            $this->syncCustomerLoyalty($people);

            $this->command?->info(sprintf(
                'Seed OK: +%d đơn hàng mới, +%d dòng sổ kho mới, +%d phiếu nhập hoàn tất mới, +%d phiếu nhập demo (chờ duyệt/đã duyệt/từ chối) mới, %d ca POS cập nhật.',
                $stats['orders'], $stats['ledger'], $stats['phieu'], $stats['pending'], $stats['shifts']
            ));
        });
    }

    private function ensureRoles(): void
    {
        $table = Schema::hasTable('vaiTro') ? 'vaiTro' : 'vaitro';
        $nameCol = Schema::hasColumn($table, 'tenVaiTro') ? 'tenVaiTro' : 'ten_vaitro';
        $descCol = Schema::hasColumn($table, 'moTa') ? 'moTa' : 'mo_ta';
        $created = Schema::hasColumn($table, 'createdAt') ? 'createdAt' : 'created_at';
        $updated = Schema::hasColumn($table, 'updatedAt') ? 'updatedAt' : 'updated_at';
        $now = Carbon::now();

        $roles = [
            1 => ['ADMIN', 'Quản trị hệ thống'], 2 => ['MANAGER', 'Quản lý cửa hàng'], 3 => ['CASHIER', 'Nhân viên bán hàng POS'],
            4 => ['WAREHOUSE', 'Nhân viên kho'], 5 => ['CSKH', 'Nhân viên tư vấn CSKH'], 6 => ['CUSTOMER', 'Khách hàng mua sắm'],
            7 => ['ACCOUNTANT', 'Nhân viên kế toán'], 8 => ['DIRECTOR', 'Ban Giám đốc'],
        ];
        foreach ($roles as $id => [$name, $desc]) {
            if (!DB::table($table)->where('id', $id)->exists()) {
                DB::table($table)->insert(['id' => $id, $nameCol => $name, $descCol => $desc, $created => $now, $updated => $now]);
            }
        }
    }

    private function ensureStores(): array
    {
        $now = Carbon::now();
        $ids = [];
        foreach ($this->storeDefs() as $ord => $def) {
            $id = DB::table('cuahang')->where('tenCuaHang', $def['tenCuaHang'])->value('id');
            if (!$id) {
                $id = DB::table('cuahang')->insertGetId($def + [
                    'maTinh' => 202, 'maHuyen' => 1442, 'maXa' => '20101', 'trangThai' => 1,
                    'createdAt' => $now, 'updatedAt' => $now,
                ]);
            }
            $ids[$ord] = (int) $id;
        }
        return $ids;
    }

    private function ensureSuppliers(): array
    {
        $now = Carbon::now();
        $ids = [];
        foreach ($this->supplierDefs() as $letter => $def) {
            $id = DB::table('nhaCungCap')->where('tenNcc', $def['tenNcc'])->value('id');
            if (!$id) {
                $id = DB::table('nhaCungCap')->insertGetId($def + ['trangThai' => 1, 'createdAt' => $now, 'updatedAt' => $now]);
            }
            $ids[$letter] = (int) $id;
        }
        return $ids;
    }

    private function ensureCategories(): array
    {
        $now = Carbon::now();
        $ids = [];
        foreach ($this->categoryDefs() as $key => $def) {
            $id = DB::table('loaiSanPham')->where('slug', $def['slug'])->value('id');
            if (!$id) {
                $id = DB::table('loaiSanPham')->insertGetId($def + ['trangThai' => 1, 'createdAt' => $now, 'updatedAt' => $now]);
            }
            $ids[$key] = (int) $id;
        }
        return $ids;
    }

    private function ensureProducts(array $products, array $categoryIds): array
    {
        $now = Carbon::now();
        $ids = [];
        foreach ($products as $idx => $p) {
            $id = DB::table('sanPham')->where('slug', $p['slug'])->value('id');
            if (!$id) {
                $id = DB::table('sanPham')->insertGetId([
                    'loaiId' => $categoryIds[$p['loaiId']],
                    'tenSanPham' => $p['tenSanPham'],
                    'slug' => $p['slug'],
                    'giaBan' => $p['giaBan'],
                    'hinhAnh' => $p['hinhAnh'],
                    'moTa' => 'Sản phẩm demo Givral Bakery: ' . $p['tenSanPham'] . '.',
                    'donViTinh' => 'Cái',
                    'trangThai' => 1,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            }
            $ids[$idx] = (int) $id;
        }
        return $ids;
    }

    private function ensureAccounts(array $storeIds): array
    {
        $now = Carbon::now();
        $passwordHash = Hash::make(self::PASSWORD);
        $staff = [];
        $customers = [];

        foreach ($this->staffDefs() as $i => $d) {
            $tkId = DB::table('taikhoan')->where('username', $d['username'])->value('id');
            if (!$tkId) {
                $tkId = DB::table('taikhoan')->insertGetId([
                    'username' => $d['username'], 'email' => $d['username'] . '@givral.test', 'password' => $passwordHash,
                    'vaiTroId' => $d['vaiTroId'], 'trangThai' => 1, 'createdAt' => $now, 'updatedAt' => $now,
                ]);
            }
            $ndId = DB::table('nguoidung')->where('taiKhoanId', $tkId)->value('id');
            if (!$ndId) {
                $ndId = DB::table('nguoidung')->insertGetId([
                    'taiKhoanId' => $tkId,
                    'cuaHangId' => $d['store'] ? $storeIds[$d['store']] : null,
                    'hoTen' => $d['hoTen'],
                    'soDienThoai' => '0909' . sprintf('%06d', 100000 + $i * 7919),
                    'gioiTinh' => $d['gioiTinh'],
                    'cccdNumber' => null,
                    'loaiNguoiDung' => 1,
                    'caLamViec' => $d['ca'],
                    'luong' => $d['luong'],
                    'createdAt' => $now, 'updatedAt' => $now,
                ]);
            }
            $staff[$d['store'] . '|' . $d['key']] = (int) $ndId;
        }

        foreach ($this->customerDefs() as $i => $d) {
            $tkId = DB::table('taikhoan')->where('username', $d['username'])->value('id');
            if (!$tkId) {
                $tkId = DB::table('taikhoan')->insertGetId([
                    'username' => $d['username'], 'email' => $d['username'] . '@givral.test', 'password' => $passwordHash,
                    'vaiTroId' => 6, 'trangThai' => 1, 'createdAt' => $now, 'updatedAt' => $now,
                ]);
            }
            $ndId = DB::table('nguoidung')->where('taiKhoanId', $tkId)->value('id');
            if (!$ndId) {
                $ndId = DB::table('nguoidung')->insertGetId([
                    'taiKhoanId' => $tkId, 'cuaHangId' => null, 'hoTen' => $d['hoTen'], 'soDienThoai' => $d['soDienThoai'],
                    'gioiTinh' => $d['gioiTinh'], 'loaiNguoiDung' => 0, 'createdAt' => $now, 'updatedAt' => $now,
                ]);
            }
            $customers[$i] = (int) $ndId;
        }

        return ['staff' => $staff, 'customers' => $customers];
    }

    private function chunkInsert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function persistOrders(array $plan, array $storeIds, array $productIds, array $people): int
    {
        $existing = DB::table('donHang')
            ->where('maDonHang', 'like', 'POS-%')
            ->orWhere('maDonHang', 'like', 'HD-%')
            ->pluck('id', 'maDonHang')
            ->all();

        $lineRows = [];
        $historyRows = [];
        $created = 0;

        foreach ($plan['orders'] as $o) {
            if (isset($existing[$o['code']])) {
                continue;
            }
            $isOnline = $o['type'] === 'ONLINE';
            $deliveredAt = Carbon::parse($o['ts'])->addMinutes(210)->format('Y-m-d H:i:s');

            $orderId = DB::table('donHang')->insertGetId([
                'maDonHang' => $o['code'],
                'nguoidungId' => $o['customer'] !== null ? $people['customers'][$o['customer']] : null,
                'cuaHangId' => $storeIds[$o['store']],
                'loaiDonHang' => $isOnline ? 0 : 1,
                'tongTienHang' => $o['subtotal'],
                'soTienGiam' => 0,
                'phiVanChuyen' => $o['ship'],
                'tongThanhToan' => $o['total'],
                'phuongThucThanhToan' => $o['pm'],
                'trangThaiThanhToan' => $o['paid'],
                'trangThaiDonHang' => $o['status'],
                'maVanDonGhn' => ($isOnline && $o['status'] >= 2) ? 'GHN' . strtoupper(substr(md5($o['code']), 0, 9)) : null,
                'ghnStatusCode' => $isOnline ? ($o['status'] === 5 ? 'delivered' : ['1' => 'created', '2' => 'ready_to_pick', '3' => 'picked', '4' => 'delivering'][(string) $o['status']]) : null,
                'diaChiGiaoHang' => $o['addr'],
                'createdAt' => $o['ts'],
                'updatedAt' => ($isOnline && $o['status'] === 5) ? $deliveredAt : $o['ts'],
            ]);
            $created++;

            foreach ($o['lines'] as [$pIdx, $qty]) {
                $price = $this->priceOf($plan, $pIdx);
                $lineRows[] = [
                    'donHangId' => $orderId, 'sanPhamId' => $productIds[$pIdx], 'soLuong' => $qty,
                    'donGia' => $price, 'thanhTien' => $price * $qty, 'createdAt' => $o['ts'], 'updatedAt' => $o['ts'],
                ];
            }

            if ($isOnline) {
                $historyRows = array_merge($historyRows, $this->buildOnlineTimeline($orderId, $o));
            }
        }

        $this->chunkInsert('chiTietDonHang', $lineRows);
        $this->chunkInsert('lichSuTrangThaiDonHang', $historyRows);
        return $created;
    }

    /** Giá bán đơn vị (giữ ở plan để seeder không phụ thuộc DB). */
    private ?array $priceCache = null;

    private function priceOf(array $plan, int $pIdx): int
    {
        $this->priceCache ??= array_map(fn ($p) => $p['giaBan'], $this->productDefs());
        return $this->priceCache[$pIdx];
    }

    private function buildOnlineTimeline(int $orderId, array $o): array
    {
        $pm = $o['pm'];
        $steps = [
            [0, 1, $pm === 0 ? 'created_cod' : ($pm === 1 ? 'paid_vietqr' : 'paid_momo'),
                $pm === 0 ? 'Khách đặt đơn hàng Online (COD - Chưa thanh toán)' : ($pm === 1 ? 'Khách đặt đơn hàng chuyển khoản VietQR' : 'Khách đặt đơn hàng thanh toán Ví MoMo'), 2, 0],
            [1, 2, 'ready_to_pick', 'Cửa hàng đã xác nhận & bàn giao đơn cho đơn vị vận chuyển', 1, 25],
            [2, 3, 'picked', 'Đơn vị vận chuyển đã lấy hàng', 0, 90],
            [3, 4, 'delivering', 'Đang giao hàng đến khách', 0, 150],
            [4, 5, 'delivered', 'Giao hàng thành công', 0, 210],
        ];
        $rows = [];
        foreach ($steps as [$from, $to, $code, $desc, $source, $offset]) {
            if ($to > $o['status']) {
                break;
            }
            $at = Carbon::parse($o['ts'])->addMinutes($offset)->format('Y-m-d H:i:s');
            $rows[] = [
                'donHangId' => $orderId, 'trangThaiCu' => $from, 'trangThaiMoi' => $to, 'ghnStatusCode' => $code,
                'moTaChiTiet' => $desc, 'nguonCapNhat' => $source, 'createdAt' => $at, 'updatedAt' => $at,
            ];
        }
        return $rows;
    }

    private function persistLedger(array $plan, array $storeIds, array $productIds): int
    {
        $existingRows = DB::table('nhatKyKho')
            ->where('maThamChieu', 'like', 'HDN-DEMO-%')
            ->orWhere('maThamChieu', 'like', 'POS-%')
            ->orWhere('maThamChieu', 'like', 'HD-%')
            ->get(['maThamChieu', 'cuaHangId', 'sanPhamId', 'loaiBienDong']);
        $existing = [];
        foreach ($existingRows as $r) {
            $existing["{$r->maThamChieu}|{$r->cuaHangId}|{$r->sanPhamId}|{$r->loaiBienDong}"] = true;
        }

        $rows = [];
        foreach ($plan['ledger'] as $l) {
            $storeId = $storeIds[$l['store']];
            $productId = $productIds[$l['p']];
            if (isset($existing["{$l['ref']}|{$storeId}|{$productId}|{$l['type']}"])) {
                continue;
            }
            $rows[] = [
                'cuaHangId' => $storeId, 'sanPhamId' => $productId, 'loaiBienDong' => $l['type'],
                'soLuongThayDoi' => $l['delta'], 'soLuongTonSau' => $l['after'], 'maThamChieu' => $l['ref'],
                'createdAt' => $l['ts'], 'updatedAt' => $l['ts'],
            ];
        }
        $this->chunkInsert('nhatKyKho', $rows);
        return count($rows);
    }

    private function persistStock(array $plan, array $storeIds, array $productIds): void
    {
        $now = Carbon::now();
        foreach ($plan['stockFinal'] as $key => $qty) {
            [$s, $p] = array_map('intval', explode('|', $key));
            $where = ['cuaHangId' => $storeIds[$s], 'sanPhamId' => $productIds[$p]];
            if (DB::table('tonKhoChiNhanh')->where($where)->exists()) {
                DB::table('tonKhoChiNhanh')->where($where)->update(['soLuongTon' => $qty, 'updatedAt' => $now]);
            } else {
                DB::table('tonKhoChiNhanh')->insert($where + ['soLuongTon' => $qty, 'createdAt' => $now, 'updatedAt' => $now]);
            }
        }
    }

    private function persistCompletedPurchaseOrders(array $plan, array $products, array $storeIds, array $supplierIds, array $productIds, array $people): int
    {
        $existing = DB::table('hoaDonNhap')->where('maHoaDonNhap', 'like', 'HDN-DEMO-%')->pluck('id', 'maHoaDonNhap')->all();
        $created = 0;

        foreach ($plan['phieu'] as $ph) {
            if (isset($existing[$ph['code']])) {
                continue;
            }
            $s = $ph['store'];
            $managerId = $people['staff']["{$s}|quanly"];
            $khoId = $people['staff']["{$s}|kho"];
            $ketoanId = $people['staff']["{$s}|ketoan"];
            $byManager = $ph['creator'] === 'MANAGER';

            $lines = array_map(fn ($i) => $plan['imports'][$i], $ph['lines']);
            $total = 0;
            foreach ($lines as $ln) {
                $total += $ln['ordered'] * $products[$ln['p']]['giaNhap'];
            }

            $hdnId = DB::table('hoaDonNhap')->insertGetId([
                'maHoaDonNhap' => $ph['code'],
                'nhaCungCapId' => $supplierIds[$ph['supplier']],
                'cuaHangId' => $storeIds[$s],
                'nguoiNhapId' => $byManager ? $managerId : $khoId,
                'nguoiTaoRole' => $ph['creator'],
                'nguoiDuyetId' => $managerId,
                'thoiGianDuyet' => $byManager ? $ph['createdAt'] : $ph['approvedAt'],
                'ngayNhap' => $ph['createdAt'],
                'tongTien' => $total,
                'ghiChu' => 'Nhập hàng định kỳ theo nhu cầu bán (dữ liệu demo)',
                'trangThai' => PurchaseOrderStatus::HOAN_TAT->toLegacyInt(),
                'maTrangThai' => PurchaseOrderStatus::HOAN_TAT->value,
                'tonKhoDaCong' => 1,
                'createdAt' => $ph['createdAt'],
                'updatedAt' => $ph['completedAt'],
            ]);
            $created++;

            $detailRows = [];
            foreach ($lines as $ln) {
                $giaNhap = $products[$ln['p']]['giaNhap'];
                $loId = DB::table('loHang')->insertGetId([
                    'maLoHang' => $ln['lotCode'],
                    'sanPhamId' => $productIds[$ln['p']],
                    'cuaHangId' => $storeIds[$s],
                    'nhaCungCapId' => $supplierIds[$ph['supplier']],
                    'soLuongNhap' => $ln['ordered'],
                    'soLuongCon' => $plan['lotRemaining'][$ln['lotKey']] ?? 0,
                    'ngaySanXuat' => $ln['nsx'],
                    'hsd' => $ln['hsd'],
                    'giaNhap' => $giaNhap,
                    'trangThai' => ($plan['lotRemaining'][$ln['lotKey']] ?? 0) > 0 ? 0 : 1,
                    'createdAt' => $ph['createdAt'],
                    'updatedAt' => $ph['completedAt'],
                ]);
                $detailRows[] = [
                    'hoaDonNhapId' => $hdnId, 'loHangId' => $loId, 'sanPhamId' => $productIds[$ln['p']],
                    'soLuong' => $ln['ordered'], 'soLuongThucNhan' => $ln['qty'], 'ghiChuChenhLech' => $ln['note'],
                    'donGiaNhap' => $giaNhap, 'thanhTien' => $ln['ordered'] * $giaNhap,
                ];
            }
            $this->chunkInsert('chiTietHoaDonNhap', $detailRows);

            $date = $ph['date'];
            $at = fn (string $hm) => "{$date} {$hm}:00";
            if ($byManager) {
                $sigs = [
                    [PurchaseOrderSignatureRole::QUAN_LY_CHI_NHANH, $managerId, null, $at('08:00')],
                    [PurchaseOrderSignatureRole::NHAN_VIEN_GIAO_HANG, null, $ph['driver'], $at('08:05')],
                ];
            } else {
                $sigs = [
                    [PurchaseOrderSignatureRole::NHAN_VIEN_VAN_CHUYEN, null, $ph['driver'], $at('08:00')],
                    [PurchaseOrderSignatureRole::NHAN_VIEN_KE_TOAN, $ketoanId, null, $at('08:05')],
                    [PurchaseOrderSignatureRole::NHAN_VIEN_KHO, $khoId, null, $at('08:10')],
                ];
            }
            $sigRows = [];
            foreach ($sigs as [$role, $signerId, $driverName, $time]) {
                $sigRows[] = [
                    'hoaDonNhapId' => $hdnId, 'vaiTroKy' => $role->value, 'nguoiKyId' => $signerId,
                    'tenNguoiKy' => $driverName ?? $this->nameOfProfile($signerId),
                    'chuKyAnh' => null, 'ghiChu' => 'Đã kiểm kê & ký xác nhận (dữ liệu demo)',
                    'thoiGianKy' => $time, 'created_at' => $time, 'updated_at' => $time,
                ];
            }
            $this->chunkInsert('purchase_order_signatures', $sigRows);
        }
        return $created;
    }

    private array $profileNameCache = [];

    private function nameOfProfile(?int $profileId): ?string
    {
        if (!$profileId) {
            return null;
        }
        return $this->profileNameCache[$profileId] ??= (string) DB::table('nguoidung')->where('id', $profileId)->value('hoTen');
    }

    private function persistPendingPurchaseOrders(array $plan, array $products, array $storeIds, array $supplierIds, array $productIds, array $people): int
    {
        $created = 0;
        foreach ($plan['pending'] as $pd) {
            $s = $pd['store'];
            /** @var PurchaseOrderStatus $status */
            $status = $pd['status'];
            $code = sprintf('HDN-DEMO-PEND-C%d-%s', $s, $status->value);
            if (DB::table('hoaDonNhap')->where('maHoaDonNhap', $code)->exists()) {
                continue;
            }

            $managerId = $people['staff']["{$s}|quanly"];
            $khoId = $people['staff']["{$s}|kho"];
            $byManager = $pd['creator'] === 'MANAGER';
            $total = 0;
            foreach ($pd['items'] as [$p, $qty]) {
                $total += $qty * $products[$p]['giaNhap'];
            }

            $approved = in_array($status, [PurchaseOrderStatus::DA_DUYET, PurchaseOrderStatus::TU_CHOI], true);
            $hdnId = DB::table('hoaDonNhap')->insertGetId([
                'maHoaDonNhap' => $code,
                'nhaCungCapId' => $supplierIds[$pd['supplier']],
                'cuaHangId' => $storeIds[$s],
                'nguoiNhapId' => $byManager ? $managerId : $khoId,
                'nguoiTaoRole' => $pd['creator'],
                'nguoiDuyetId' => $approved ? $managerId : null,
                'thoiGianDuyet' => $approved ? $pd['approvedAt'] : null,
                'lyDoTuChoi' => $status === PurchaseOrderStatus::TU_CHOI ? $pd['reason'] : null,
                'ngayNhap' => $pd['createdAt'],
                'tongTien' => $total,
                'ghiChu' => 'Phiếu nhập demo trạng thái ' . $status->label() . ' (không cộng tồn kho)',
                'trangThai' => $status->toLegacyInt(),
                'maTrangThai' => $status->value,
                'tonKhoDaCong' => 0,
                'createdAt' => $pd['createdAt'],
                'updatedAt' => $pd['approvedAt'] ?? $pd['createdAt'],
            ]);
            $created++;

            $detailRows = [];
            foreach ($pd['items'] as [$p, $qty]) {
                $prod = $products[$p];
                $nsx = $pd['date'];
                $loId = DB::table('loHang')->insertGetId([
                    'maLoHang' => sprintf('LH-DEMO-PEND-C%d-%s-P%02d', $s, $status->value, $p + 1),
                    'sanPhamId' => $productIds[$p],
                    'cuaHangId' => $storeIds[$s],
                    'nhaCungCapId' => $supplierIds[$pd['supplier']],
                    'soLuongNhap' => $qty,
                    'soLuongCon' => 0, // chưa nhập kho cho tới khi kiểm kê & đủ chữ ký (giống PurchaseOrderService)
                    'ngaySanXuat' => $nsx,
                    'hsd' => Carbon::parse($nsx)->addDays($prod['shelf'])->format('Y-m-d'),
                    'giaNhap' => $prod['giaNhap'],
                    'trangThai' => 0,
                    'createdAt' => $pd['createdAt'],
                    'updatedAt' => $pd['createdAt'],
                ]);
                $detailRows[] = [
                    'hoaDonNhapId' => $hdnId, 'loHangId' => $loId, 'sanPhamId' => $productIds[$p], 'soLuong' => $qty,
                    'soLuongThucNhan' => null, 'ghiChuChenhLech' => null,
                    'donGiaNhap' => $prod['giaNhap'], 'thanhTien' => $qty * $prod['giaNhap'],
                ];
            }
            $this->chunkInsert('chiTietHoaDonNhap', $detailRows);
        }
        return $created;
    }

    private function persistShifts(array $plan, array $storeIds, array $people): int
    {
        $count = 0;
        foreach ($plan['shifts'] as $sh) {
            $nhanVienId = $people['staff']["{$sh['store']}|{$sh['cashierKey']}"];
            $key = ['cuaHangId' => $storeIds[$sh['store']], 'nhanVienId' => $nhanVienId, 'thoiGianMoCa' => $sh['open']];
            $values = [
                'thoiGianDongCa' => $sh['close'],
                'tienMatDauCa' => $sh['opening'],
                'doanhThuTienMat' => $sh['cash'],
                'doanhThuChuyenKhoan' => $sh['bank'],
                'doanhThuMomo' => $sh['momo'],
                'tongDoanhThu' => $sh['total'],
                'tienMatSach' => $sh['expectedCash'],
                'tienMatThucTe' => $sh['expectedCash'],
                'chenhLech' => 0,
                'trangThai' => 1,
                'updatedAt' => $sh['close'],
            ];
            if (DB::table('posShifts')->where($key)->exists()) {
                DB::table('posShifts')->where($key)->update($values);
            } else {
                DB::table('posShifts')->insert($key + $values + ['createdAt' => $sh['open']]);
            }
            $count++;
        }
        return $count;
    }

    /** Điểm tích luỹ / hạng thành viên theo đúng công thức hệ thống (đơn đã giao: trạng thái 4,5; 10.000đ = 1 điểm). */
    private function syncCustomerLoyalty(array $people): void
    {
        foreach ($people['customers'] as $ndId) {
            $spent = (float) DB::table('donHang')->where('nguoidungId', $ndId)->whereIn('trangThaiDonHang', [4, 5])->sum('tongThanhToan');
            $points = (int) floor($spent / 10000);
            $rank = $points >= 1000 ? 'Thách đấu' : ($points >= 500 ? 'Kim cương' : ($points >= 200 ? 'Vàng' : ($points >= 50 ? 'Bạc' : 'Đồng')));
            DB::table('nguoidung')->where('id', $ndId)->update(['tongChiTieu' => $spent, 'diemTichLuy' => $points, 'hangThanhVien' => $rank]);
        }
    }

    // ------------------------------------------------------------------
    // REPORT (CHỈ SELECT)
    // ------------------------------------------------------------------
    private function report(array $products): void
    {
        $cmd = $this->command;
        if (!$cmd) {
            return;
        }
        $isDemoOrder = fn ($q) => $q->where('maDonHang', 'like', 'POS-%')->orWhere('maDonHang', 'like', 'HD-%');
        $roleNames = [1 => 'ADMIN', 2 => 'MANAGER', 3 => 'CASHIER', 4 => 'WAREHOUSE', 5 => 'CSKH', 6 => 'CUSTOMER', 7 => 'ACCOUNTANT', 8 => 'DIRECTOR'];

        $cmd->newLine();
        $cmd->info('================ BÁO CÁO KIỂM TRA (chỉ SELECT) ================');

        // 1. Tài khoản
        $usernames = array_column($this->staffDefs(), 'username');
        $acc = DB::table('taikhoan as t')
            ->join('nguoidung as n', 'n.taiKhoanId', '=', 't.id')
            ->leftJoin('cuahang as c', 'c.id', '=', 'n.cuaHangId')
            ->whereIn('t.username', $usernames)
            ->groupBy('t.vaiTroId', 'c.tenCuaHang')
            ->orderBy('c.tenCuaHang')->orderBy('t.vaiTroId')
            ->selectRaw("t.vaiTroId as vt, COALESCE(c.tenCuaHang,'(không thuộc chi nhánh)') as cn, COUNT(*) as n")
            ->get();
        $cmd->info('1) Tài khoản nhân sự theo role & chi nhánh (tổng ' . $acc->sum('n') . ')');
        $cmd->table(['Chi nhánh', 'Role', 'Số TK'], $acc->map(fn ($r) => [$r->cn, ($roleNames[$r->vt] ?? $r->vt), $r->n])->all());
        $cmd->line('   Khách hàng mẫu: ' . DB::table('taikhoan')->where('username', 'like', 'khach\_%')->count());

        // 2. Đơn & doanh thu
        $byStore = DB::table('donHang as d')->leftJoin('cuahang as c', 'c.id', '=', 'd.cuaHangId')
            ->where($isDemoOrder)
            ->groupBy('c.tenCuaHang', 'd.loaiDonHang')->orderBy('c.tenCuaHang')->orderBy('d.loaiDonHang')
            ->selectRaw('c.tenCuaHang as cn, d.loaiDonHang as loai, COUNT(*) as so_don, SUM(d.tongThanhToan) as dt')->get();
        $cmd->info('2) Số đơn & doanh thu theo chi nhánh / hình thức');
        $cmd->table(['Chi nhánh', 'Hình thức', 'Số đơn', 'Doanh thu (đ)'], $byStore->map(fn ($r) => [$r->cn, (int) $r->loai === 1 ? 'POS' : 'Online', $r->so_don, number_format((float) $r->dt, 0, ',', '.')])->all());
        $tot = $byStore->sum('so_don');
        $pos = $byStore->where('loai', 1)->sum('so_don');
        $cmd->line(sprintf('   Tổng: %d đơn, doanh thu %s đ, POS %.1f%% / Online %.1f%%',
            $tot, number_format((float) $byStore->sum('dt'), 0, ',', '.'), $tot ? $pos * 100 / $tot : 0, $tot ? ($tot - $pos) * 100 / $tot : 0));

        $perDay = DB::table('donHang')->where($isDemoOrder)->groupBy(DB::raw('DATE(createdAt)'))
            ->selectRaw('DATE(createdAt) as ngay, COUNT(*) as so_don, SUM(tongThanhToan) as dt')->orderByDesc('dt')->get();
        $cmd->info('   Top 5 ngày doanh thu cao nhất (toàn hệ thống):');
        $holidays = $this->holidayMap();
        $cmd->table(['Ngày', 'Số đơn', 'Doanh thu (đ)', 'Ghi chú'], $perDay->take(5)->map(fn ($r) => [$r->ngay, $r->so_don, number_format((float) $r->dt, 0, ',', '.'), $holidays[$r->ngay] ?? (Carbon::parse($r->ngay)->isWeekend() ? 'Cuối tuần' : '')])->all());
        $avg = function ($rows) { return $rows->count() ? $rows->avg('dt') : 0; };
        $holidayRows = $perDay->filter(fn ($r) => isset($holidays[$r->ngay]));
        $weekendRows = $perDay->filter(fn ($r) => !isset($holidays[$r->ngay]) && Carbon::parse($r->ngay)->isWeekend());
        $weekdayRows = $perDay->filter(fn ($r) => !isset($holidays[$r->ngay]) && !Carbon::parse($r->ngay)->isWeekend());
        $cmd->line(sprintf('   Doanh thu TB/ngày: ngày thường %s đ | cuối tuần %s đ | ngày lễ %s đ (x%.2f so với ngày thường)',
            number_format($avg($weekdayRows), 0, ',', '.'), number_format($avg($weekendRows), 0, ',', '.'), number_format($avg($holidayRows), 0, ',', '.'),
            $avg($weekdayRows) > 0 ? $avg($holidayRows) / $avg($weekdayRows) : 0));

        // 3. Tồn kho
        $demoIds = DB::table('sanPham')->where('slug', 'like', 'demo-%')->pluck('id')->all();
        $stockRows = DB::table('tonKhoChiNhanh')->whereIn('sanPhamId', $demoIds)->get();
        $cmd->info(sprintf('3) Tồn kho SP demo: %d dòng | min=%d | max=%d | số dòng âm=%d | số dòng > %d = %d',
            $stockRows->count(), $stockRows->min('soLuongTon'), $stockRows->max('soLuongTon'),
            $stockRows->where('soLuongTon', '<', 0)->count(), self::MAX_STOCK, $stockRows->where('soLuongTon', '>', self::MAX_STOCK)->count()));
        $allStock = DB::table('tonKhoChiNhanh');
        $cmd->line(sprintf('   (Toàn bảng tồn kho kể cả dữ liệu có sẵn: min=%s, max=%s, số dòng > %d = %d)',
            (clone $allStock)->min('soLuongTon'), (clone $allStock)->max('soLuongTon'), self::MAX_STOCK, (clone $allStock)->where('soLuongTon', '>', self::MAX_STOCK)->count()));
        $ledgerQuery = fn () => DB::table('nhatKyKho')->whereIn('sanPhamId', $demoIds)->where(function ($q) {
            $q->where('maThamChieu', 'like', 'HDN-DEMO-%')->orWhere('maThamChieu', 'like', 'POS-%')->orWhere('maThamChieu', 'like', 'HD-%');
        });
        $cmd->line(sprintf('   Sổ kho (tồn sau mỗi biến động): min=%s, max=%s => %s',
            $ledgerQuery()->min('soLuongTonSau'), $ledgerQuery()->max('soLuongTonSau'),
            ($ledgerQuery()->min('soLuongTonSau') >= 0 && $ledgerQuery()->max('soLuongTonSau') <= self::MAX_STOCK) ? 'KHÔNG âm tại bất kỳ thời điểm nào, <= 50' : 'CÓ VI PHẠM'));

        // 4. Đối soát nhập - bán = tồn
        $imports = DB::table('chiTietHoaDonNhap as ct')->join('hoaDonNhap as h', 'h.id', '=', 'ct.hoaDonNhapId')
            ->where('h.tonKhoDaCong', 1)->where('h.maHoaDonNhap', 'like', 'HDN-DEMO-%')
            ->groupBy('h.cuaHangId', 'ct.sanPhamId')->selectRaw('h.cuaHangId as s, ct.sanPhamId as p, SUM(COALESCE(ct.soLuongThucNhan, ct.soLuong)) as q')->get();
        $sold = DB::table('chiTietDonHang as ct')->join('donHang as d', 'd.id', '=', 'ct.donHangId')
            ->where(function ($q) { $q->where('d.maDonHang', 'like', 'POS-%')->orWhere('d.maDonHang', 'like', 'HD-%'); })
            ->groupBy('d.cuaHangId', 'ct.sanPhamId')->selectRaw('d.cuaHangId as s, ct.sanPhamId as p, SUM(ct.soLuong) as q')->get();
        $ledgerSum = DB::table('nhatKyKho')->whereIn('sanPhamId', $demoIds)->where(function ($q) {
            $q->where('maThamChieu', 'like', 'HDN-DEMO-%')->orWhere('maThamChieu', 'like', 'POS-%')->orWhere('maThamChieu', 'like', 'HD-%');
        })->groupBy('cuaHangId', 'sanPhamId')->selectRaw('cuaHangId as s, sanPhamId as p, SUM(soLuongThayDoi) as q')->get();
        $lotSum = DB::table('loHang')->whereIn('sanPhamId', $demoIds)->where('maLoHang', 'like', 'LH-DEMO-%')
            ->groupBy('cuaHangId', 'sanPhamId')->selectRaw('cuaHangId as s, sanPhamId as p, SUM(soLuongCon) as q')->get();

        $index = fn ($rows) => $rows->mapWithKeys(fn ($r) => ["{$r->s}|{$r->p}" => (int) $r->q])->all();
        [$imp, $sol, $led, $lot] = [$index($imports), $index($sold), $index($ledgerSum), $index($lotSum)];
        $stockMap = $stockRows->mapWithKeys(fn ($r) => ["{$r->cuaHangId}|{$r->sanPhamId}" => (int) $r->soLuongTon])->all();

        $badBalance = $badLedger = $badLot = 0;
        $samples = [];
        foreach ($stockMap as $k => $stock) {
            $calc = ($imp[$k] ?? 0) - ($sol[$k] ?? 0);
            if ($calc !== $stock) {
                $badBalance++;
                if (count($samples) < 5) {
                    $samples[] = [$k, $imp[$k] ?? 0, $sol[$k] ?? 0, $stock];
                }
            }
            if (($led[$k] ?? 0) !== $stock) {
                $badLedger++;
            }
            if (($lot[$k] ?? 0) !== $stock) {
                $badLot++;
            }
        }
        $cmd->info(sprintf('4) Đối soát %d cặp (chi nhánh, sản phẩm): lệch (nhập - bán != tồn) = %d | lệch (tổng sổ kho != tồn) = %d | lệch (tổng lô còn != tồn) = %d',
            count($stockMap), $badBalance, $badLedger, $badLot));
        if ($samples) {
            $cmd->table(['Cặp (cn|sp)', 'Nhập', 'Bán', 'Tồn'], $samples);
        }

        // 5. Phiếu nhập
        $po = DB::table('hoaDonNhap')->where('maHoaDonNhap', 'like', 'HDN-DEMO-%')->groupBy('maTrangThai', 'nguoiTaoRole', 'tonKhoDaCong')
            ->selectRaw('maTrangThai as st, nguoiTaoRole as role, tonKhoDaCong as cong, COUNT(*) as n')->orderBy('maTrangThai')->get();
        $cmd->info('5) Phiếu nhập hàng demo theo trạng thái');
        $cmd->table(['Trạng thái', 'Người tạo', 'Đã cộng tồn kho', 'Số phiếu'], $po->map(fn ($r) => [$r->st, $r->role, $r->cong ? 'có' : 'không', $r->n])->all());
        $cmd->line('   Phiếu CHƯA hoàn tất nhưng tonKhoDaCong=1 (phải = 0): ' . DB::table('hoaDonNhap')->where('maHoaDonNhap', 'like', 'HDN-DEMO-%')->where('maTrangThai', '!=', 'HOAN_TAT')->where('tonKhoDaCong', 1)->count());
        $cmd->line('   Mật khẩu mọi tài khoản demo: ' . self::PASSWORD . ' (email @givral.test)');
    }
}

