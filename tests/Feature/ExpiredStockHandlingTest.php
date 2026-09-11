<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LoHang;
use App\Models\SanPham;
use App\Models\LoaiSanPham;
use App\Models\CuaHang;
use App\Models\TonKhoCuaHang;
use App\Models\TaiKhoan;
use App\Models\KhachHang;
use App\Models\BienBanHuy;
use App\Http\Controllers\Api\ProductController;
use Carbon\Carbon;

class ExpiredStockHandlingTest extends TestCase
{
    /**
     * Test lô hàng hết hạn bị loại trừ khỏi tồn kho khả dụng và không thể thêm vào giỏ hàng
     */
    public function test_expired_batch_is_excluded_from_available_stock_and_cart_addition()
    {
        LoaiSanPham::firstOrCreate(['MALOAI' => 1], ['TENLOAI' => 'Bánh Mì Test']);

        $store = CuaHang::firstOrCreate(['MACUAHANG' => 1], [
            'TENCUAHANG' => 'Cửa hàng Chi Nhánh 1',
            'DIACHI'     => '123 Test Street',
            'TRANGTHAI'  => 'Đang hoạt động'
        ]);

        $user = TaiKhoan::firstOrCreate(
            ['USERNAME' => 'test_buyer_expired'],
            [
                'PASSWORD' => 'password123',
                'MAROLE'   => 3,
                'EMAIL'    => 'buyer_exp@bakery.com',
            ]
        );

        KhachHang::firstOrCreate(
            ['USERNAME' => 'test_buyer_expired'],
            [
                'MAKH'   => 'KH_EXP_01',
                'HOTEN'  => 'Khách Hàng Test',
                'EMAIL'  => 'buyer_exp@bakery.com',
                'SDT'    => '0901234567',
            ]
        );

        $sp = SanPham::firstOrCreate(['MASP' => 'SPEX01'], [
            'TENSP'    => 'Bánh Mì Hết Hạn Test',
            'GIABAN'   => 50000,
            'SOLUONG'  => 10,
            'MALOAI'   => 1
        ]);

        TonKhoCuaHang::updateOrCreate(
            ['MACUAHANG' => 1, 'MASP' => 'SPEX01'],
            ['SOLUONG_TON' => 10]
        );

        // Lô 1: Đã hết hạn ngày hôm qua
        LoHang::updateOrCreate(
            ['MALOHAN' => 'LH_EX01'],
            [
                'MASP'         => 'SPEX01',
                'MACUAHANG'    => 1,
                'NGAYNHAP'     => Carbon::yesterday()->subDays(5),
                'HSD'          => Carbon::yesterday(),
                'SOLUONG_NHAP' => 10,
                'SOLUONG_CON'  => 10,
                'TRANGTHAI'    => 'con_hang',
            ]
        );

        $productController = new ProductController();
        $availStock = $productController->calculateStock('SPEX01', 1);

        // Số lượng tồn khả dụng phải bằng 0 do lô 1 đã hết hạn
        $this->assertEquals(0, $availStock);

        // Thử thêm vào giỏ hàng -> phải thất bại vì tồn kho còn hạn = 0
        $response = $this->actingAs($user, 'api')->postJson('/api/cart/add', [
            'masp'     => 'SPEX01',
            'quantity' => 1
        ]);

        $response->assertStatus(400)
                 ->assertJsonFragment(['error' => 'Sản phẩm đã hết hạn sử dụng hoặc không đủ tồn kho còn hạn!']);
    }

    /**
     * Test tự động loại trừ lô hết hạn và tạo biên bản hủy ở trạng thái cho_ky chờ xác nhận
     */
    public function test_auto_expire_creates_pending_cancellation_record_waiting_for_signature()
    {
        LoaiSanPham::firstOrCreate(['MALOAI' => 1], ['TENLOAI' => 'Bánh Mì Test']);

        $store = CuaHang::firstOrCreate(['MACUAHANG' => 1], [
            'TENCUAHANG' => 'Cửa hàng Chi Nhánh 1',
            'DIACHI'     => '456 Test Street',
            'TRANGTHAI'  => 'Đang hoạt động'
        ]);

        $sp = SanPham::firstOrCreate(['MASP' => 'SPEX02'], [
            'TENSP'    => 'Bánh Kem Hết Hạn Test',
            'GIABAN'   => 120000,
            'SOLUONG'  => 5,
            'MALOAI'   => 1
        ]);

        LoHang::updateOrCreate(
            ['MALOHAN' => 'LH_EX02'],
            [
                'MASP'         => 'SPEX02',
                'MACUAHANG'    => 1,
                'NGAYNHAP'     => Carbon::yesterday()->subDays(10),
                'HSD'          => Carbon::yesterday(),
                'SOLUONG_NHAP' => 5,
                'SOLUONG_CON'  => 5,
                'TRANGTHAI'    => 'con_hang',
            ]
        );

        // Chạy autoExpireLo
        $loService = new \App\Services\LoHangService();
        $result = $loService->autoExpireLo();

        $this->assertGreaterThanOrEqual(1, $result['expired_count']);

        // Kiểm tra lô đã bị trừ về 0 và chuyển sang 'bi_huy'
        $this->assertDatabaseHas('lo_hang', [
            'MALOHAN'     => 'LH_EX02',
            'SOLUONG_CON' => 0,
            'TRANGTHAI'   => 'bi_huy'
        ]);

        // Kiểm tra đã có biên bản hủy tự động tạo với trạng thái cho_ky
        $this->assertDatabaseHas('bien_ban_huy', [
            'MALOHAN'      => 'LH_EX02',
            'LY_DO'        => 'het_han',
            'TRANGTHAI_KY' => 'cho_ky'
        ]);
    }
}
