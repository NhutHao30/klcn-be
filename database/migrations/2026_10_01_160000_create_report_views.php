<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW v_thong_ke_doanh_thu AS
            SELECT 
                d.id AS donHangId,
                d.maDonHang,
                d.cuaHangId,
                d.loaiDonHang,
                d.tongThanhToan,
                d.trangThaiThanhToan,
                d.trangThaiDonHang,
                d.createdAt,
                DATE(d.createdAt) AS ngayTao,
                MONTH(d.createdAt) AS thangTao,
                YEAR(d.createdAt) AS namTao,
                d.diaChiGiaoHang
            FROM donHang d
            WHERE d.trangThaiThanhToan = 1 OR d.trangThaiDonHang IN (4, 5)
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_top_san_pham_ban_chay AS
            SELECT 
                sp.id AS sanPhamId,
                sp.tenSanPham AS TENSP,
                d.cuaHangId,
                MONTH(d.createdAt) AS thangTao,
                YEAR(d.createdAt) AS namTao,
                SUM(ct.soLuong) AS total_sold,
                SUM(ct.thanhTien) AS total_revenue
            FROM chiTietDonHang ct
            JOIN donHang d ON ct.donHangId = d.id
            JOIN sanPham sp ON ct.sanPhamId = sp.id
            WHERE d.trangThaiThanhToan = 1 OR d.trangThaiDonHang IN (4, 5)
            GROUP BY sp.id, sp.tenSanPham, d.cuaHangId, MONTH(d.createdAt), YEAR(d.createdAt)
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_top_dia_ban_giao_hang AS
            SELECT 
                d.diaChiGiaoHang,
                d.cuaHangId,
                COUNT(*) AS total_orders,
                SUM(d.tongThanhToan) AS total_revenue
            FROM donHang d
            WHERE (d.trangThaiThanhToan = 1 OR d.trangThaiDonHang IN (4, 5))
              AND d.diaChiGiaoHang IS NOT NULL AND d.diaChiGiaoHang != ''
            GROUP BY d.diaChiGiaoHang, d.cuaHangId
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_thong_ke_doanh_thu");
        DB::statement("DROP VIEW IF EXISTS v_top_san_pham_ban_chay");
        DB::statement("DROP VIEW IF EXISTS v_top_dia_ban_giao_hang");
    }
};
