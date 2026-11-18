<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_create_hoadon_nhap');
        DB::unprepared("
CREATE PROCEDURE sp_create_hoadon_nhap(
    IN p_nhacungcap_id BIGINT,
    IN p_cuahang_id INT,
    IN p_nguoi_nhap_id BIGINT,
    IN p_ghi_chu TEXT,
    IN p_auto_sign_all INT,
    IN p_ky_quan_ly LONGTEXT,
    IN p_items_json JSON,
    OUT p_hoadon_id BIGINT,
    OUT p_ma_hoadon VARCHAR(50),
    OUT p_error VARCHAR(255)
)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE n INT DEFAULT 0;
    DECLARE v_sp_id INT;
    DECLARE v_qty INT;
    DECLARE v_don_gia DECIMAL(12,2);
    DECLARE v_nsx DATE;
    DECLARE v_hsd DATE;
    DECLARE v_thanh_tien DECIMAL(15,2);
    DECLARE v_tong_tien DECIMAL(15,2) DEFAULT 0;
    DECLARE v_ma_hd VARCHAR(50);
    DECLARE v_ma_lo VARCHAR(50);
    DECLARE v_lo_id BIGINT;
    DECLARE v_manager_signed TINYINT DEFAULT 0;
    DECLARE v_stock_qty INT;
    DECLARE v_ten_nguoi_nhap VARCHAR(150);

    SET p_hoadon_id = NULL;
    SET p_ma_hoadon = NULL;
    SET p_error = NULL;
    SET n = JSON_LENGTH(p_items_json);

    IF n IS NULL OR n = 0 THEN
        SET p_error = 'Chi tiết sản phẩm nhập không được rỗng';
    ELSE
        SELECT COALESCE(hoTen, 'Quản lý chi nhánh') INTO v_ten_nguoi_nhap FROM nguoidung WHERE id = p_nguoi_nhap_id LIMIT 1;
        SET v_manager_signed = IF(p_auto_sign_all = 1 OR (p_ky_quan_ly IS NOT NULL AND p_ky_quan_ly != ''), 1, 0);
        SET v_ma_hd = CONCAT('HDN-', UPPER(SUBSTRING(MD5(RAND()), 1, 10)));

        INSERT INTO hoadonnhap (
            maHoaDonNhap, nhaCungCapId, cuaHangId, nguoiNhapId, ngayNhap, tongTien, ghiChu, trangThai, createdAt, updatedAt
        ) VALUES (
            v_ma_hd, p_nhacungcap_id, p_cuahang_id, p_nguoi_nhap_id, NOW(), 0, p_ghi_chu, v_manager_signed, NOW(), NOW()
        );
        SET p_hoadon_id = LAST_INSERT_ID();
        SET p_ma_hoadon = v_ma_hd;

        -- Tạo 3 vị trí ký: 1 = Nhân viên kế toán, 2 = Nhân viên kho, 3 = Quản lý chi nhánh
        -- Nếu Quản lý chi nhánh (vaiTroKy = 3) đã ký duyệt thì đơn hoàn tất luôn, không cần Kho & Kế toán ký nữa.
        INSERT INTO chukyso (maChuKy, loaiTaiLieu, taiLieuId, vaiTroKy, nguoiKyId, tenNguoiKy, digitalSignatureHash, publicKey, trangThai, ngayKy, ghiChu, createdAt, updatedAt)
        VALUES
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R1-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 1, NULL, 'Nhân viên kế toán', NULL, NULL, 0, NULL, NULL, NOW(), NOW()),
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R2-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 2, NULL, 'Nhân viên kho', NULL, NULL, 0, NULL, NULL, NOW(), NOW()),
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R3-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 3,
            IF(v_manager_signed = 1, p_nguoi_nhap_id, NULL),
            IF(v_manager_signed = 1, v_ten_nguoi_nhap, 'Quản lý chi nhánh'),
            IF(v_manager_signed = 1, COALESCE(NULLIF(p_ky_quan_ly, ''), SHA2(CONCAT('HDN_', p_hoadon_id, '_R3_', UNIX_TIMESTAMP()), 256)), NULL),
            IF(v_manager_signed = 1, 'BRANCH_MANAGER_SIGNATURE', NULL),
            v_manager_signed,
            IF(v_manager_signed = 1, NOW(), NULL),
            IF(v_manager_signed = 1, 'Quản lý chi nhánh đã ký duyệt đơn nhập hàng', NULL),
            NOW(), NOW()
        );

        WHILE i < n DO
            SET v_sp_id = CAST(JSON_UNQUOTE(COALESCE(
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanPhamId')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].MASP'))
            )) AS UNSIGNED);
            SET v_qty = CAST(JSON_UNQUOTE(COALESCE(
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].soLuong')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].SOLUONGTN')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].SOLUONGTCT')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].SOLUONG'))
            )) AS UNSIGNED);
            SET v_don_gia = CAST(JSON_UNQUOTE(COALESCE(
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].donGiaNhap')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].don_gia_nhap')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].DONGIANHAP'))
            )) AS DECIMAL(12,2));
            SET v_nsx = NULLIF(JSON_UNQUOTE(COALESCE(
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].ngaySanXuat')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].ngay_san_xuat')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].NGAYSX'))
            )), 'null');
            SET v_hsd = JSON_UNQUOTE(COALESCE(
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].hsd')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].HSD_LO')),
                JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].HSD'))
            ));

            SET v_thanh_tien = v_qty * v_don_gia;
            SET v_tong_tien = v_tong_tien + v_thanh_tien;
            SET v_ma_lo = CONCAT('LH-', p_hoadon_id, '-', v_sp_id, '-', UPPER(SUBSTRING(MD5(RAND()), 1, 4)));

            INSERT INTO lohang (
                maLoHang, sanPhamId, cuaHangId, nhaCungCapId, soLuongNhap, soLuongCon, ngaySanXuat, hsd, giaNhap, trangThai, createdAt, updatedAt
            ) VALUES (
                v_ma_lo, v_sp_id, p_cuahang_id, p_nhacungcap_id, v_qty, v_qty, v_nsx, v_hsd, v_don_gia, 0, NOW(), NOW()
            );
            SET v_lo_id = LAST_INSERT_ID();

            INSERT INTO chitiethoadonnhap (
                hoaDonNhapId, loHangId, sanPhamId, soLuong, donGiaNhap, thanhTien
            ) VALUES (
                p_hoadon_id, v_lo_id, v_sp_id, v_qty, v_don_gia, v_thanh_tien
            );

            IF v_manager_signed = 1 THEN
                INSERT INTO tonkhochinhanh (cuaHangId, sanPhamId, soLuongTon, createdAt, updatedAt)
                VALUES (p_cuahang_id, v_sp_id, v_qty, NOW(), NOW())
                ON DUPLICATE KEY UPDATE soLuongTon = soLuongTon + v_qty, updatedAt = NOW();

                SELECT soLuongTon INTO v_stock_qty FROM tonkhochinhanh WHERE cuaHangId = p_cuahang_id AND sanPhamId = v_sp_id LIMIT 1;

                INSERT INTO nhatkykho (cuaHangId, sanPhamId, loaiBienDong, soLuongThayDoi, soLuongTonSau, maThamChieu, createdAt, updatedAt)
                VALUES (p_cuahang_id, v_sp_id, 4, v_qty, v_stock_qty, v_ma_hd, NOW(), NOW());
            END IF;

            SET i = i + 1;
        END WHILE;

        UPDATE hoadonnhap SET tongTien = v_tong_tien WHERE id = p_hoadon_id;
    END IF;
END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_create_bien_ban_huy');
        DB::unprepared("
CREATE PROCEDURE sp_create_bien_ban_huy(
    IN p_cuahang_id INT,
    IN p_nguoi_lap_id BIGINT,
    IN p_ly_do VARCHAR(255),
    IN p_auto_sign_all INT,
    IN p_items_json JSON,
    OUT p_bienban_id BIGINT,
    OUT p_ma_bien_ban VARCHAR(50),
    OUT p_error VARCHAR(255)
)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE n INT DEFAULT 0;
    DECLARE v_lo_id BIGINT;
    DECLARE v_qty_destroy INT;
    DECLARE v_sp_id INT;
    DECLARE v_qty_con INT;
    DECLARE v_don_gia DECIMAL(12,2) DEFAULT 0;
    DECLARE v_thanh_tien DECIMAL(15,2) DEFAULT 0;
    DECLARE v_tong_gia_tri DECIMAL(15,2) DEFAULT 0;
    DECLARE v_ma_bb VARCHAR(50);
    DECLARE v_manager_signed TINYINT DEFAULT 0;
    DECLARE v_new_batch_qty INT;
    DECLARE v_batch_status TINYINT;
    DECLARE v_stock_qty INT DEFAULT 0;
    DECLARE v_ten_nguoi_lap VARCHAR(150);

    SET p_bienban_id = NULL;
    SET p_ma_bien_ban = NULL;
    SET p_error = NULL;
    SET n = JSON_LENGTH(p_items_json);

    IF n IS NULL OR n = 0 THEN
        SET p_error = 'Chi tiết sản phẩm hủy không được rỗng';
    ELSE
        SELECT COALESCE(hoTen, 'Quản lý chi nhánh') INTO v_ten_nguoi_lap FROM nguoidung WHERE id = p_nguoi_lap_id LIMIT 1;
        SET v_ma_bb = CONCAT('BBH-', UPPER(SUBSTRING(MD5(RAND()), 1, 10)));
        SET v_manager_signed = IF(p_auto_sign_all = 1, 1, 0);

        INSERT INTO bienbanhuy (
            maBienBan, cuaHangId, nguoiLapId, ngayLap, lyDo, tongGiaTriHuy, trangThai, createdAt, updatedAt
        ) VALUES (
            v_ma_bb, p_cuahang_id, p_nguoi_lap_id, NOW(), p_ly_do, 0, v_manager_signed, NOW(), NOW()
        );
        SET p_bienban_id = LAST_INSERT_ID();
        SET p_ma_bien_ban = v_ma_bb;

        -- Tạo 3 vị trí ký: 1 = Nhân viên kế toán, 2 = Nhân viên kho, 3 = Quản lý chi nhánh
        INSERT INTO chukyso (maChuKy, loaiTaiLieu, taiLieuId, vaiTroKy, nguoiKyId, tenNguoiKy, digitalSignatureHash, publicKey, trangThai, ngayKy, ghiChu, createdAt, updatedAt)
        VALUES
        (CONCAT('SIG-HUY-', p_bienban_id, '-R1-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 2, p_bienban_id, 1, NULL, 'Nhân viên kế toán', NULL, NULL, 0, NULL, NULL, NOW(), NOW()),
        (CONCAT('SIG-HUY-', p_bienban_id, '-R2-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 2, p_bienban_id, 2, NULL, 'Nhân viên kho', NULL, NULL, 0, NULL, NULL, NOW(), NOW()),
        (CONCAT('SIG-HUY-', p_bienban_id, '-R3-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 2, p_bienban_id, 3,
            IF(v_manager_signed = 1, p_nguoi_lap_id, NULL),
            IF(v_manager_signed = 1, v_ten_nguoi_lap, 'Quản lý chi nhánh'),
            IF(v_manager_signed = 1, SHA2(CONCAT('BBH_', p_bienban_id, '_R3_', UNIX_TIMESTAMP()), 256), NULL),
            IF(v_manager_signed = 1, 'BRANCH_MANAGER_SIGNATURE', NULL),
            v_manager_signed,
            IF(v_manager_signed = 1, NOW(), NULL),
            IF(v_manager_signed = 1, 'Quản lý chi nhánh đã ký duyệt biên bản hủy', NULL),
            NOW(), NOW()
        );

        WHILE i < n DO
            SET v_lo_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].lohang_id'))) AS UNSIGNED);
            SET v_qty_destroy = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong_huy'))) AS UNSIGNED);
            SELECT sanPhamId, COALESCE(soLuongCon, 0), COALESCE(giaNhap, 0)
            INTO v_sp_id, v_qty_con, v_don_gia
            FROM lohang WHERE id = v_lo_id LIMIT 1;

            IF v_sp_id IS NOT NULL THEN
                IF v_qty_destroy > v_qty_con AND v_qty_con > 0 THEN
                    SET v_qty_destroy = v_qty_con;
                END IF;
                SET v_thanh_tien = v_qty_destroy * v_don_gia;
                SET v_tong_gia_tri = v_tong_gia_tri + v_thanh_tien;

                INSERT INTO chitietbienbanhuy (
                    bienBanHuyId, loHangId, sanPhamId, soLuongHuy, donGia, thanhTien
                ) VALUES (
                    p_bienban_id, v_lo_id, v_sp_id, v_qty_destroy, v_don_gia, v_thanh_tien
                );

                IF v_manager_signed = 1 THEN
                    SET v_new_batch_qty = GREATEST(0, v_qty_con - v_qty_destroy);
                    SET v_batch_status = IF(v_new_batch_qty = 0, 3, 2);
                    UPDATE lohang SET soLuongCon = v_new_batch_qty, trangThai = v_batch_status, updatedAt = NOW() WHERE id = v_lo_id;
                    UPDATE tonkhochinhanh SET soLuongTon = GREATEST(0, soLuongTon - v_qty_destroy), updatedAt = NOW()
                    WHERE cuaHangId = p_cuahang_id AND sanPhamId = v_sp_id;
                    SELECT COALESCE(soLuongTon, 0) INTO v_stock_qty FROM tonkhochinhanh WHERE cuaHangId = p_cuahang_id AND sanPhamId = v_sp_id LIMIT 1;
                    INSERT INTO nhatkykho (cuaHangId, sanPhamId, loaiBienDong, soLuongThayDoi, soLuongTonSau, maThamChieu, createdAt, updatedAt)
                    VALUES (p_cuahang_id, v_sp_id, 5, -v_qty_destroy, v_stock_qty, v_ma_bb, NOW(), NOW());
                END IF;
            END IF;
            SET i = i + 1;
        END WHILE;

        UPDATE bienbanhuy SET tongGiaTriHuy = v_tong_gia_tri WHERE id = p_bienban_id;
    END IF;
END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_sign_role');
        DB::unprepared("
CREATE PROCEDURE sp_sign_role(
    IN p_chu_ky_so_id BIGINT,
    IN p_nguoi_ky_id BIGINT,
    IN p_raw_payload TEXT,
    IN p_public_key TEXT,
    IN p_ghi_chu VARCHAR(255),
    OUT p_is_fully_signed INT,
    OUT p_error VARCHAR(255)
)
BEGIN
    DECLARE v_status TINYINT;
    DECLARE v_loai_tai_lieu TINYINT;
    DECLARE v_tai_lieu_id BIGINT;
    DECLARE v_vai_tro_ky TINYINT;
    DECLARE v_sig_hash TEXT;
    DECLARE v_r1_signed INT DEFAULT 0;
    DECLARE v_r2_signed INT DEFAULT 0;
    DECLARE v_r3_signed INT DEFAULT 0;
    DECLARE v_doc_already_done INT DEFAULT 0;
    DECLARE v_ten_nguoi_ky VARCHAR(150);
    DECLARE v_cuahang_id INT;
    DECLARE v_ma_hd VARCHAR(50);
    DECLARE v_ma_bb VARCHAR(50);
    DECLARE done INT DEFAULT FALSE;
    DECLARE c_sp_id INT;
    DECLARE c_qty INT;
    DECLARE c_stock_qty INT DEFAULT 0;
    DECLARE c_lo_id BIGINT;
    DECLARE c_qty_destroy INT;
    DECLARE c_batch_qty INT DEFAULT 0;
    DECLARE c_new_batch_qty INT DEFAULT 0;

    DECLARE cur1 CURSOR FOR
        SELECT sanPhamId, soLuong FROM chitiethoadonnhap WHERE hoaDonNhapId = v_tai_lieu_id;
    DECLARE cur2 CURSOR FOR
        SELECT loHangId, sanPhamId, soLuongHuy FROM chitietbienbanhuy WHERE bienBanHuyId = v_tai_lieu_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    SET p_is_fully_signed = 0;
    SET p_error = NULL;

    SELECT trangThai, loaiTaiLieu, taiLieuId, vaiTroKy
    INTO v_status, v_loai_tai_lieu, v_tai_lieu_id, v_vai_tro_ky
    FROM chukyso WHERE id = p_chu_ky_so_id LIMIT 1;

    IF v_status IS NULL THEN
        SET p_error = 'Không tìm thấy vị trí chữ ký số';
    ELSEIF v_status = 1 THEN
        SET p_error = 'Vị trí vai trò này đã được ký xác nhận trước đó';
    ELSE
        SELECT COALESCE(hoTen, 'Người ký duyệt') INTO v_ten_nguoi_ky FROM nguoidung WHERE id = p_nguoi_ky_id LIMIT 1;
        IF p_raw_payload LIKE 'data:image/%' THEN
            SET v_sig_hash = p_raw_payload;
        ELSE
            SET v_sig_hash = SHA2(CONCAT(p_raw_payload, '_ROLE_', v_vai_tro_ky, '_', UNIX_TIMESTAMP()), 256);
        END IF;

        UPDATE chukyso
        SET nguoiKyId = p_nguoi_ky_id,
            tenNguoiKy = COALESCE(v_ten_nguoi_ky, tenNguoiKy),
            digitalSignatureHash = v_sig_hash,
            publicKey = COALESCE(p_public_key, 'SYSTEM_RSA_PUBLIC_KEY'),
            trangThai = 1,
            ngayKy = NOW(),
            ghiChu = COALESCE(p_ghi_chu, 'Đã ký xác nhận thành công'),
            updatedAt = NOW()
        WHERE id = p_chu_ky_so_id;

        -- Kiểm tra điều kiện hoàn tất ký duyệt:
        -- 1. Quản lý chi nhánh (vaiTroKy = 3) đã ký -> Hoàn tất ngay (không cần Kho & Kế toán ký nữa)
        -- 2. Hoặc cả Nhân viên kế toán (vaiTroKy = 1) VÀ Nhân viên kho (vaiTroKy = 2) đều đã ký -> Hoàn tất
        SELECT
            MAX(CASE WHEN vaiTroKy = 1 AND trangThai = 1 THEN 1 ELSE 0 END),
            MAX(CASE WHEN vaiTroKy = 2 AND trangThai = 1 THEN 1 ELSE 0 END),
            MAX(CASE WHEN vaiTroKy = 3 AND trangThai = 1 THEN 1 ELSE 0 END)
        INTO v_r1_signed, v_r2_signed, v_r3_signed
        FROM chukyso
        WHERE loaiTaiLieu = v_loai_tai_lieu AND taiLieuId = v_tai_lieu_id;

        IF v_r3_signed = 1 OR (v_r1_signed = 1 AND v_r2_signed = 1) THEN
            SET p_is_fully_signed = 1;

            IF v_loai_tai_lieu = 1 THEN
                SELECT cuaHangId, maHoaDonNhap, trangThai INTO v_cuahang_id, v_ma_hd, v_doc_already_done
                FROM hoadonnhap WHERE id = v_tai_lieu_id LIMIT 1;

                IF v_doc_already_done = 0 THEN
                    UPDATE hoadonnhap SET trangThai = 1, updatedAt = NOW() WHERE id = v_tai_lieu_id;
                    OPEN cur1;
                    read_cur1: LOOP
                        FETCH cur1 INTO c_sp_id, c_qty;
                        IF done THEN LEAVE read_cur1; END IF;
                        INSERT INTO tonkhochinhanh (cuaHangId, sanPhamId, soLuongTon, createdAt, updatedAt)
                        VALUES (v_cuahang_id, c_sp_id, c_qty, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE soLuongTon = soLuongTon + c_qty, updatedAt = NOW();
                        SELECT soLuongTon INTO c_stock_qty FROM tonkhochinhanh WHERE cuaHangId = v_cuahang_id AND sanPhamId = c_sp_id LIMIT 1;
                        INSERT INTO nhatkykho (cuaHangId, sanPhamId, loaiBienDong, soLuongThayDoi, soLuongTonSau, maThamChieu, createdAt, updatedAt)
                        VALUES (v_cuahang_id, c_sp_id, 4, c_qty, c_stock_qty, v_ma_hd, NOW(), NOW());
                    END LOOP;
                    CLOSE cur1;
                END IF;
            ELSEIF v_loai_tai_lieu = 2 THEN
                SELECT cuaHangId, maBienBan, trangThai INTO v_cuahang_id, v_ma_bb, v_doc_already_done
                FROM bienbanhuy WHERE id = v_tai_lieu_id LIMIT 1;

                IF v_doc_already_done = 0 THEN
                    UPDATE bienbanhuy SET trangThai = 1, updatedAt = NOW() WHERE id = v_tai_lieu_id;
                    OPEN cur2;
                    read_cur2: LOOP
                        FETCH cur2 INTO c_lo_id, c_sp_id, c_qty_destroy;
                        IF done THEN LEAVE read_cur2; END IF;
                        SELECT COALESCE(soLuongCon, 0) INTO c_batch_qty FROM lohang WHERE id = c_lo_id LIMIT 1;
                        SET c_new_batch_qty = GREATEST(0, c_batch_qty - c_qty_destroy);
                        UPDATE lohang SET soLuongCon = c_new_batch_qty, trangThai = IF(c_new_batch_qty = 0, 3, 2), updatedAt = NOW() WHERE id = c_lo_id;
                        UPDATE tonkhochinhanh SET soLuongTon = GREATEST(0, soLuongTon - c_qty_destroy), updatedAt = NOW() WHERE cuaHangId = v_cuahang_id AND sanPhamId = c_sp_id;
                        SELECT COALESCE(soLuongTon, 0) INTO c_stock_qty FROM tonkhochinhanh WHERE cuaHangId = v_cuahang_id AND sanPhamId = c_sp_id LIMIT 1;
                        INSERT INTO nhatkykho (cuaHangId, sanPhamId, loaiBienDong, soLuongThayDoi, soLuongTonSau, maThamChieu, createdAt, updatedAt)
                        VALUES (v_cuahang_id, c_sp_id, 5, -c_qty_destroy, c_stock_qty, v_ma_bb, NOW(), NOW());
                    END LOOP;
                    CLOSE cur2;
                END IF;
            END IF;
        END IF;
    END IF;
END
        ");
    }

    public function down(): void
    {
        // Keep updated procedures
    }
};

