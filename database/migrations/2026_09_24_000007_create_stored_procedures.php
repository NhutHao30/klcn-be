<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. sp_open_pos_shift
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_open_pos_shift");
        DB::unprepared("
            CREATE PROCEDURE sp_open_pos_shift(
                IN p_cuahang_id INT,
                IN p_nhanvien_id BIGINT,
                IN p_tien_mat_dau_ca DECIMAL(15,2),
                OUT p_shift_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE v_active_count INT DEFAULT 0;
                
                SET p_shift_id = NULL;
                SET p_error = NULL;

                SELECT COUNT(*) INTO v_active_count
                FROM pos_shifts
                WHERE nhanvien_id = p_nhanvien_id AND trang_thai = 0;

                IF v_active_count > 0 THEN
                    SET p_error = 'Bạn đang có một ca làm việc đang mở';
                ELSE
                    INSERT INTO pos_shifts (
                        cuahang_id, nhanvien_id, thoi_gian_mo_ca, tien_mat_dau_ca, tien_mat_sach, trang_thai, created_at, updated_at
                    ) VALUES (
                        p_cuahang_id, p_nhanvien_id, NOW(), p_tien_mat_dau_ca, p_tien_mat_dau_ca, 0, NOW(), NOW()
                    );
                    SET p_shift_id = LAST_INSERT_ID();
                END IF;
            END
        ");

        // 2. sp_close_pos_shift
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_close_pos_shift");
        DB::unprepared("
            CREATE PROCEDURE sp_close_pos_shift(
                IN p_nhanvien_id BIGINT,
                IN p_tien_mat_thuc_te DECIMAL(15,2),
                OUT p_shift_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE v_shift_id BIGINT DEFAULT NULL;
                DECLARE v_cuahang_id INT DEFAULT NULL;
                DECLARE v_mo_ca DATETIME DEFAULT NULL;
                DECLARE v_tien_dau DECIMAL(15,2) DEFAULT 0;
                
                DECLARE v_rev_cash DECIMAL(15,2) DEFAULT 0;
                DECLARE v_rev_bank DECIMAL(15,2) DEFAULT 0;
                DECLARE v_rev_momo DECIMAL(15,2) DEFAULT 0;
                DECLARE v_rev_total DECIMAL(15,2) DEFAULT 0;

                DECLARE v_tien_sach DECIMAL(15,2) DEFAULT 0;
                DECLARE v_chenh_lech DECIMAL(15,2) DEFAULT 0;

                SET p_shift_id = NULL;
                SET p_error = NULL;

                SELECT id, cuahang_id, thoi_gian_mo_ca, tien_mat_dau_ca
                INTO v_shift_id, v_cuahang_id, v_mo_ca, v_tien_dau
                FROM pos_shifts
                WHERE nhanvien_id = p_nhanvien_id AND trang_thai = 0
                LIMIT 1;

                IF v_shift_id IS NULL THEN
                    SET p_error = 'Không tìm thấy ca làm việc đang mở';
                ELSE
                    -- Doanh thu Tiền mặt (0)
                    SELECT COALESCE(SUM(tong_thanhtoan), 0) INTO v_rev_cash
                    FROM don_hang
                    WHERE cuahang_id = v_cuahang_id
                      AND loai_don_hang = 1
                      AND phuong_thuc_thanhtoan = 0
                      AND trang_thai_thanhtoan = 1
                      AND created_at >= v_mo_ca;

                    -- Doanh thu Chuyển khoản / VietQR (1)
                    SELECT COALESCE(SUM(tong_thanhtoan), 0) INTO v_rev_bank
                    FROM don_hang
                    WHERE cuahang_id = v_cuahang_id
                      AND loai_don_hang = 1
                      AND phuong_thuc_thanhtoan = 1
                      AND trang_thai_thanhtoan = 1
                      AND created_at >= v_mo_ca;

                    -- Doanh thu Ví MoMo (2)
                    SELECT COALESCE(SUM(tong_thanhtoan), 0) INTO v_rev_momo
                    FROM don_hang
                    WHERE cuahang_id = v_cuahang_id
                      AND loai_don_hang = 1
                      AND phuong_thuc_thanhtoan = 2
                      AND trang_thai_thanhtoan = 1
                      AND created_at >= v_mo_ca;

                    SET v_rev_total = v_rev_cash + v_rev_bank + v_rev_momo;
                    SET v_tien_sach = v_tien_dau + v_rev_cash;
                    SET v_chenh_lech = p_tien_mat_thuc_te - v_tien_sach;

                    UPDATE pos_shifts
                    SET thoi_gian_dong_ca = NOW(),
                        doanh_thu_tien_mat = v_rev_cash,
                        doanh_thu_chuyen_khoan = v_rev_bank,
                        doanh_thu_momo = v_rev_momo,
                        tong_doanh_thu = v_rev_total,
                        tien_mat_sach = v_tien_sach,
                        tien_mat_thuc_te = p_tien_mat_thuc_te,
                        chenh_lech = v_chenh_lech,
                        trang_thai = 1,
                        updated_at = NOW()
                    WHERE id = v_shift_id;

                    SET p_shift_id = v_shift_id;
                END IF;
            END
        ");

        // 3. sp_pos_checkout
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_pos_checkout");
        DB::unprepared("
            CREATE PROCEDURE sp_pos_checkout(
                IN p_cuahang_id INT,
                IN p_phuong_thuc_thanhtoan INT,
                IN p_items_json JSON,
                OUT p_donhang_id BIGINT,
                OUT p_ma_don_hang VARCHAR(30),
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE i INT DEFAULT 0;
                DECLARE n INT DEFAULT 0;
                DECLARE v_sp_id INT;
                DECLARE v_qty INT;
                DECLARE v_price DECIMAL(15,2);
                DECLARE v_thanh_tien DECIMAL(15,2);
                DECLARE v_tong_tien DECIMAL(15,2) DEFAULT 0;
                DECLARE v_ma VARCHAR(30);

                SET p_donhang_id = NULL;
                SET p_ma_don_hang = NULL;
                SET p_error = NULL;

                SET n = JSON_LENGTH(p_items_json);

                IF n IS NULL OR n = 0 THEN
                    SET p_error = 'Danh sách sản phẩm không được rỗng';
                ELSE
                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong'))) AS UNSIGNED);

                        SELECT gia_ban INTO v_price FROM san_pham WHERE id = v_sp_id LIMIT 1;
                        SET v_thanh_tien = v_price * v_qty;
                        SET v_tong_tien = v_tong_tien + v_thanh_tien;

                        SET i = i + 1;
                    END WHILE;

                    SET v_ma = CONCAT('POS', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), FLOOR(10 + RAND() * 90));

                    INSERT INTO don_hang (
                        ma_don_hang, cuahang_id, loai_don_hang, tong_tien_hang, so_tien_giam, phi_van_chuyen,
                        tong_thanhtoan, phuong_thuc_thanhtoan, trang_thai_thanhtoan, trang_thai_don_hang, created_at, updated_at
                    ) VALUES (
                        v_ma, p_cuahang_id, 1, v_tong_tien, 0, 0, v_tong_tien, p_phuong_thuc_thanhtoan, 1, 5, NOW(), NOW()
                    );

                    SET p_donhang_id = LAST_INSERT_ID();
                    SET p_ma_don_hang = v_ma;

                    SET i = 0;
                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong'))) AS UNSIGNED);

                        SELECT gia_ban INTO v_price FROM san_pham WHERE id = v_sp_id LIMIT 1;
                        SET v_thanh_tien = v_price * v_qty;

                        INSERT INTO chitiet_don_hang (
                            donhang_id, sanpham_id, so_luong, don_gia, thanh_tien, created_at, updated_at
                        ) VALUES (
                            p_donhang_id, v_sp_id, v_qty, v_price, v_thanh_tien, NOW(), NOW()
                        );

                        -- Trừ số lượng tồn kho chi nhánh khi bán tại quầy POS
                        INSERT INTO tonkho_chinhanh (cuahang_id, sanpham_id, so_luong_ton, created_at, updated_at)
                        VALUES (p_cuahang_id, v_sp_id, 0, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE 
                            so_luong_ton = GREATEST(0, CAST(so_luong_ton AS SIGNED) - v_qty),
                            updated_at = NOW();

                        SET i = i + 1;
                    END WHILE;
                END IF;
            END
        ");

        // 4. sp_checkout_online
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_checkout_online");
        DB::unprepared("
            CREATE PROCEDURE sp_checkout_online(
                IN p_nguoidung_id BIGINT,
                IN p_cuahang_id INT,
                IN p_dia_chi_giao_hang VARCHAR(255),
                IN p_phuong_thuc_thanhtoan INT,
                IN p_ma_voucher VARCHAR(30),
                IN p_ghi_chu TEXT,
                IN p_items_json JSON,
                OUT p_donhang_id BIGINT,
                OUT p_ma_don_hang VARCHAR(30),
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE i INT DEFAULT 0;
                DECLARE n INT DEFAULT 0;
                DECLARE v_sp_id INT;
                DECLARE v_qty INT;
                DECLARE v_price DECIMAL(15,2);
                DECLARE v_thanh_tien DECIMAL(15,2);
                DECLARE v_tong_tien DECIMAL(15,2) DEFAULT 0;
                DECLARE v_so_tien_giam DECIMAL(15,2) DEFAULT 0;
                DECLARE v_voucher_id INT DEFAULT NULL;
                DECLARE v_loai_giam TINYINT DEFAULT 0;
                DECLARE v_gia_tri_giam DECIMAL(15,2) DEFAULT 0;
                DECLARE v_don_hang_min DECIMAL(15,2) DEFAULT 0;
                DECLARE v_giam_max DECIMAL(15,2) DEFAULT NULL;
                DECLARE v_v_trang_thai TINYINT DEFAULT 0;
                DECLARE v_v_so_luong INT DEFAULT 0;
                DECLARE v_v_da_su_dung INT DEFAULT 0;
                DECLARE v_v_hsd_end DATETIME;
                DECLARE v_phi_ship DECIMAL(15,2) DEFAULT 15000;
                DECLARE v_tong_thanhtoan DECIMAL(15,2) DEFAULT 0;
                DECLARE v_ma VARCHAR(30);

                SET p_donhang_id = NULL;
                SET p_ma_don_hang = NULL;
                SET p_error = NULL;

                SET n = JSON_LENGTH(p_items_json);
                IF n IS NULL OR n = 0 THEN
                    SET p_error = 'Danh sách sản phẩm không được rỗng';
                ELSE
                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong'))) AS UNSIGNED);

                        SELECT gia_ban INTO v_price FROM san_pham WHERE id = v_sp_id LIMIT 1;
                        SET v_thanh_tien = v_price * v_qty;
                        SET v_tong_tien = v_tong_tien + v_thanh_tien;
                        SET i = i + 1;
                    END WHILE;

                    IF p_ma_voucher IS NOT NULL AND p_ma_voucher != '' THEN
                        SELECT id, loai_giam_gia, gia_tri_giam, don_hang_toi_thieu, giam_toi_da, trang_thai, so_luong, da_su_dung, ngay_ket_thuc
                        INTO v_voucher_id, v_loai_giam, v_gia_tri_giam, v_don_hang_min, v_giam_max, v_v_trang_thai, v_v_so_luong, v_v_da_su_dung, v_v_hsd_end
                        FROM voucher
                        WHERE ma_voucher = p_ma_voucher
                        LIMIT 1;

                        IF v_voucher_id IS NOT NULL AND v_v_trang_thai = 1 AND NOW() <= v_v_hsd_end AND v_v_da_su_dung < v_v_so_luong AND v_tong_tien >= v_don_hang_min THEN
                            IF v_loai_giam = 0 THEN
                                SET v_so_tien_giam = (v_tong_tien * v_gia_tri_giam) / 100;
                                IF v_giam_max IS NOT NULL AND v_giam_max > 0 AND v_so_tien_giam > v_giam_max THEN
                                    SET v_so_tien_giam = v_giam_max;
                                END IF;
                            ELSE
                                SET v_so_tien_giam = v_gia_tri_giam;
                            END IF;

                            UPDATE voucher SET da_su_dung = da_su_dung + 1 WHERE id = v_voucher_id;
                        ELSE
                            SET v_voucher_id = NULL;
                            SET v_so_tien_giam = 0;
                        END IF;
                    END IF;

                    SET v_tong_thanhtoan = v_tong_tien - v_so_tien_giam + v_phi_ship;
                    IF v_tong_thanhtoan < 0 THEN
                        SET v_tong_thanhtoan = 0;
                    END IF;

                    SET v_ma = CONCAT('DH', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), FLOOR(10 + RAND() * 90));

                    INSERT INTO don_hang (
                        ma_don_hang, nguoidung_id, cuahang_id, voucher_id, loai_don_hang, tong_tien_hang,
                        so_tien_giam, phi_van_chuyen, tong_thanhtoan, phuong_thuc_thanhtoan, trang_thai_thanhtoan,
                        trang_thai_don_hang, dia_chi_giao_hang, ghi_chu, created_at, updated_at
                    ) VALUES (
                        v_ma, p_nguoidung_id, p_cuahang_id, v_voucher_id, 0, v_tong_tien,
                        v_so_tien_giam, v_phi_ship, v_tong_thanhtoan, p_phuong_thuc_thanhtoan,
                        IF(p_phuong_thuc_thanhtoan = 0, 0, 1), 0, p_dia_chi_giao_hang, p_ghi_chu, NOW(), NOW()
                    );

                    SET p_donhang_id = LAST_INSERT_ID();
                    SET p_ma_don_hang = v_ma;

                    SET i = 0;
                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong'))) AS UNSIGNED);

                        SELECT gia_ban INTO v_price FROM san_pham WHERE id = v_sp_id LIMIT 1;
                        SET v_thanh_tien = v_price * v_qty;

                        INSERT INTO chitiet_don_hang (
                            donhang_id, sanpham_id, so_luong, don_gia, thanh_tien, created_at, updated_at
                        ) VALUES (
                            p_donhang_id, v_sp_id, v_qty, v_price, v_thanh_tien, NOW(), NOW()
                        );
                        SET i = i + 1;
                    END WHILE;

                    INSERT INTO lich_su_trang_thai_don_hang (
                        donhang_id, trang_thai_cu, trang_thai_moi, mo_ta_chi_tiet, nguon_cap_nhat, created_at, updated_at
                    ) VALUES (
                        p_donhang_id, NULL, 0, 'Đơn hàng được khởi tạo trực tuyến thành công (Stored Procedure)', 1, NOW(), NOW()
                    );
                END IF;
            END
        ");

        // 5. sp_cancel_order
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_cancel_order");
        DB::unprepared("
            CREATE PROCEDURE sp_cancel_order(
                IN p_donhang_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE v_status TINYINT;

                SET p_error = NULL;

                SELECT trang_thai_don_hang INTO v_status FROM don_hang WHERE id = p_donhang_id LIMIT 1;

                IF v_status IS NULL THEN
                    SET p_error = 'Không tìm thấy đơn hàng';
                ELSEIF v_status > 1 THEN
                    SET p_error = 'Đơn hàng đang giao hoặc đã hoàn tất, không thể hủy';
                ELSE
                    UPDATE don_hang SET trang_thai_don_hang = 6, updated_at = NOW() WHERE id = p_donhang_id;

                    INSERT INTO lich_su_trang_thai_don_hang (
                        donhang_id, trang_thai_cu, trang_thai_moi, mo_ta_chi_tiet, nguon_cap_nhat, created_at, updated_at
                    ) VALUES (
                        p_donhang_id, v_status, 6, 'Khách hàng hủy đơn thành công (Stored Procedure)', 2, NOW(), NOW()
                    );
                END IF;
            END
        ");

        // 6. sp_handle_ghn_webhook
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_handle_ghn_webhook");
        DB::unprepared("
            CREATE PROCEDURE sp_handle_ghn_webhook(
                IN p_ma_van_don VARCHAR(50),
                IN p_ghn_status_code VARCHAR(50),
                OUT p_donhang_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE v_donhang_id BIGINT DEFAULT NULL;
                DECLARE v_old_status TINYINT DEFAULT 0;
                DECLARE v_new_status TINYINT DEFAULT 0;

                SET p_donhang_id = NULL;
                SET p_error = NULL;

                SELECT id, trang_thai_don_hang INTO v_donhang_id, v_old_status
                FROM don_hang
                WHERE ma_van_don_ghn = p_ma_van_don
                LIMIT 1;

                IF v_donhang_id IS NULL THEN
                    SET p_error = 'Không tìm thấy đơn hàng tương ứng';
                ELSE
                    SET v_new_status = v_old_status;

                    IF p_ghn_status_code IN ('ready_for_picking', 'picking') THEN
                        SET v_new_status = 1;
                    ELSEIF p_ghn_status_code IN ('storing', 'transporting') THEN
                        SET v_new_status = 3;
                    ELSEIF p_ghn_status_code = 'delivering' THEN
                        SET v_new_status = 4;
                    ELSEIF p_ghn_status_code = 'delivered' THEN
                        SET v_new_status = 5;
                    ELSEIF p_ghn_status_code IN ('cancel', 'return') THEN
                        SET v_new_status = 6;
                    END IF;

                    UPDATE don_hang
                    SET ghn_status_code = p_ghn_status_code,
                        trang_thai_don_hang = v_new_status,
                        trang_thai_thanhtoan = IF(v_new_status = 5, 1, trang_thai_thanhtoan),
                        updated_at = NOW()
                    WHERE id = v_donhang_id;

                    INSERT INTO lich_su_trang_thai_don_hang (
                        donhang_id, trang_thai_cu, trang_thai_moi, ghn_status_code, mo_ta_chi_tiet, nguon_cap_nhat, created_at, updated_at
                    ) VALUES (
                        v_donhang_id, v_old_status, v_new_status, p_ghn_status_code,
                        CONCAT('GHN Webhook cập nhật trạng thái: ', p_ghn_status_code), 0, NOW(), NOW()
                    );

                    SET p_donhang_id = v_donhang_id;
                END IF;
            END
        ");

        // 7. sp_create_transfer_request
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_transfer_request");
        DB::unprepared("
            CREATE PROCEDURE sp_create_transfer_request(
                IN p_cuahang_xuat_id INT,
                IN p_cuahang_nhap_id INT,
                IN p_nguoi_tao_id BIGINT,
                IN p_ghi_chu TEXT,
                IN p_items_json JSON,
                OUT p_yeucau_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE i INT DEFAULT 0;
                DECLARE n INT DEFAULT 0;
                DECLARE v_sp_id INT;
                DECLARE v_qty INT;

                SET p_yeucau_id = NULL;
                SET p_error = NULL;
                SET n = JSON_LENGTH(p_items_json);

                IF n IS NULL OR n = 0 THEN
                    SET p_error = 'Danh sách sản phẩm chuyển không được rỗng';
                ELSE
                    INSERT INTO yeu_cau_dieu_chuyen (
                        cuahang_xuat_id, cuahang_nhap_id, nguoi_tao_id, trang_thai, ghi_chu, created_at, updated_at
                    ) VALUES (
                        p_cuahang_xuat_id, p_cuahang_nhap_id, p_nguoi_tao_id, 0, p_ghi_chu, NOW(), NOW()
                    );

                    SET p_yeucau_id = LAST_INSERT_ID();

                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong_chuyen'))) AS UNSIGNED);

                        INSERT INTO chitiet_dieu_chuyen (
                            yeucau_id, sanpham_id, so_luong_chuyen, created_at, updated_at
                        ) VALUES (
                            p_yeucau_id, v_sp_id, v_qty, NOW(), NOW()
                        );

                        SET i = i + 1;
                    END WHILE;
                END IF;
            END
        ");

        // 8. sp_update_transfer_status
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_update_transfer_status");
        DB::unprepared("
            CREATE PROCEDURE sp_update_transfer_status(
                IN p_yeucau_id BIGINT,
                IN p_trang_thai INT,
                IN p_nguoi_duyet_id BIGINT,
                OUT p_error VARCHAR(255)
            )
            BEGIN
                DECLARE v_ch_xuat INT;
                DECLARE v_ch_nhap INT;
                DECLARE done INT DEFAULT FALSE;
                DECLARE v_sp_id INT;
                DECLARE v_qty INT;
                DECLARE v_stock_xuat INT;
                DECLARE v_stock_nhap INT;

                DECLARE cur CURSOR FOR 
                    SELECT sanpham_id, so_luong_chuyen FROM chitiet_dieu_chuyen WHERE yeucau_id = p_yeucau_id;
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

                SET p_error = NULL;

                SELECT cuahang_xuat_id, cuahang_nhap_id INTO v_ch_xuat, v_ch_nhap
                FROM yeu_cau_dieu_chuyen
                WHERE id = p_yeucau_id LIMIT 1;

                IF v_ch_xuat IS NULL THEN
                    SET p_error = 'Không tìm thấy yêu cầu điều chuyển';
                ELSE
                    UPDATE yeu_cau_dieu_chuyen
                    SET trang_thai = p_trang_thai, nguoi_duyet_id = p_nguoi_duyet_id, updated_at = NOW()
                    WHERE id = p_yeucau_id;

                    IF p_trang_thai = 2 THEN
                        OPEN cur;
                        read_loop: LOOP
                            FETCH cur INTO v_sp_id, v_qty;
                            IF done THEN
                                LEAVE read_loop;
                            END IF;

                            INSERT INTO tonkho_chinhanh (cuahang_id, sanpham_id, so_luong_ton, created_at, updated_at)
                            VALUES (v_ch_xuat, v_sp_id, 0, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE so_luong_ton = GREATEST(0, so_luong_ton - v_qty), updated_at = NOW();

                            SELECT so_luong_ton INTO v_stock_xuat FROM tonkho_chinhanh WHERE cuahang_id = v_ch_xuat AND sanpham_id = v_sp_id LIMIT 1;

                            INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                            VALUES (v_ch_xuat, v_sp_id, 2, -v_qty, v_stock_xuat, CONCAT('TRF_', p_yeucau_id), NOW(), NOW());

                            INSERT INTO tonkho_chinhanh (cuahang_id, sanpham_id, so_luong_ton, created_at, updated_at)
                            VALUES (v_ch_nhap, v_sp_id, v_qty, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE so_luong_ton = so_luong_ton + v_qty, updated_at = NOW();

                            SELECT so_luong_ton INTO v_stock_nhap FROM tonkho_chinhanh WHERE cuahang_id = v_ch_nhap AND sanpham_id = v_sp_id LIMIT 1;

                            INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                            VALUES (v_ch_nhap, v_sp_id, 3, v_qty, v_stock_nhap, CONCAT('TRF_', p_yeucau_id), NOW(), NOW());

                        END LOOP;
                        CLOSE cur;
                    END IF;
                END IF;
            END
        ");

        // 9. sp_create_hoadon_nhap
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_hoadon_nhap");
        DB::unprepared("
            CREATE PROCEDURE sp_create_hoadon_nhap(
                IN p_nhacungcap_id BIGINT,
                IN p_cuahang_id INT,
                IN p_nguoi_nhap_id BIGINT,
                IN p_ghi_chu TEXT,
                IN p_auto_sign_all INT,
                IN p_ky_ncc VARCHAR(255),
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
                DECLARE v_r1_signed TINYINT DEFAULT 0;
                DECLARE v_r2_signed TINYINT DEFAULT 0;
                DECLARE v_r3_signed TINYINT DEFAULT 0;
                DECLARE v_r4_signed TINYINT DEFAULT 0;
                DECLARE v_stock_qty INT;

                SET p_hoadon_id = NULL;
                SET p_ma_hoadon = NULL;
                SET p_error = NULL;

                SET n = JSON_LENGTH(p_items_json);

                IF n IS NULL OR n = 0 THEN
                    SET p_error = 'Chi tiết sản phẩm nhập không được rỗng';
                ELSE
                    SET v_ma_hd = CONCAT('HDN-', UPPER(SUBSTRING(MD5(RAND()), 1, 10)));

                    INSERT INTO hoadon_nhap (
                        ma_hoadon_nhap, nhacungcap_id, cuahang_id, nguoi_nhap_id, ngay_nhap, tong_tien, ghi_chu, trang_thai, created_at, updated_at
                    ) VALUES (
                        v_ma_hd, p_nhacungcap_id, p_cuahang_id, p_nguoi_nhap_id, NOW(), 0, p_ghi_chu, IF(p_auto_sign_all = 1, 1, 0), NOW(), NOW()
                    );

                    SET p_hoadon_id = LAST_INSERT_ID();
                    SET p_ma_hoadon = v_ma_hd;

                    SET v_r1_signed = IF(p_auto_sign_all = 1, 1, 0);
                    SET v_r2_signed = IF(p_auto_sign_all = 1, 1, 0);
                    SET v_r3_signed = IF(p_auto_sign_all = 1, 1, 0);
                    SET v_r4_signed = IF(p_auto_sign_all = 1 OR (p_ky_ncc IS NOT NULL AND p_ky_ncc != ''), 1, 0);

                    INSERT INTO chu_ky_so (ma_chu_ky, loai_tai_lieu, tai_lieu_id, vai_tro_ky, nguoi_ky_id, ten_nguoi_ky, digital_signature_hash, public_key, trang_thai, ngay_ky, created_at, updated_at)
                    VALUES 
                    (CONCAT('SIG-HDN-', p_hoadon_id, '-R1-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 1, IF(v_r1_signed, p_nguoi_nhap_id, NULL), 'Kế toán trưởng', IF(v_r1_signed, SHA2(CONCAT('HDN_', p_hoadon_id, '_R1_', UNIX_TIMESTAMP()), 256), NULL), IF(v_r1_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_r1_signed, IF(v_r1_signed, NOW(), NULL), NOW(), NOW()),
                    (CONCAT('SIG-HDN-', p_hoadon_id, '-R2-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 2, IF(v_r2_signed, p_nguoi_nhap_id, NULL), 'Nhân viên quản lý kho', IF(v_r2_signed, SHA2(CONCAT('HDN_', p_hoadon_id, '_R2_', UNIX_TIMESTAMP()), 256), NULL), IF(v_r2_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_r2_signed, IF(v_r2_signed, NOW(), NULL), NOW(), NOW()),
                    (CONCAT('SIG-HDN-', p_hoadon_id, '-R3-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 3, IF(v_r3_signed, p_nguoi_nhap_id, NULL), 'Chủ cửa hàng / Quản lý chi nhánh', IF(v_r3_signed, SHA2(CONCAT('HDN_', p_hoadon_id, '_R3_', UNIX_TIMESTAMP()), 256), NULL), IF(v_r3_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_r3_signed, IF(v_r3_signed, NOW(), NULL), NOW(), NOW()),
                    (CONCAT('SIG-HDN-', p_hoadon_id, '-R4-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 1, p_hoadon_id, 4, IF(v_r4_signed, p_nguoi_nhap_id, NULL), 'Đại diện Nhà cung cấp', IF(p_ky_ncc IS NOT NULL AND p_ky_ncc != '', p_ky_ncc, IF(v_r4_signed, SHA2(CONCAT('HDN_', p_hoadon_id, '_R4_', UNIX_TIMESTAMP()), 256), NULL)), IF(v_r4_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_r4_signed, IF(v_r4_signed, NOW(), NULL), NOW(), NOW());

                    WHILE i < n DO
                        SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanpham_id'))) AS UNSIGNED);
                        SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong'))) AS UNSIGNED);
                        SET v_don_gia = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].don_gia_nhap'))) AS DECIMAL(12,2));
                        SET v_nsx = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].ngay_san_xuat'))), 'null');
                        SET v_hsd = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].hsd')));

                        SET v_thanh_tien = v_qty * v_don_gia;
                        SET v_tong_tien = v_tong_tien + v_thanh_tien;
                        SET v_ma_lo = CONCAT('LH-', p_hoadon_id, '-', v_sp_id, '-', UPPER(SUBSTRING(MD5(RAND()), 1, 4)));

                        INSERT INTO lo_hang (
                            ma_lo_hang, sanpham_id, cuahang_id, nhacungcap_id, so_luong_nhap, so_luong_con, ngay_san_xuat, hsd, gia_nhap, trang_thai, created_at, updated_at
                        ) VALUES (
                            v_ma_lo, v_sp_id, p_cuahang_id, p_nhacungcap_id, v_qty, v_qty, v_nsx, v_hsd, v_don_gia, 0, NOW(), NOW()
                        );

                        SET v_lo_id = LAST_INSERT_ID();

                        INSERT INTO chitiet_hoadon_nhap (
                            hoadon_nhap_id, lohang_id, sanpham_id, so_luong, don_gia_nhap, thanh_tien
                        ) VALUES (
                            p_hoadon_id, v_lo_id, v_sp_id, v_qty, v_don_gia, v_thanh_tien
                        );

                        IF p_auto_sign_all = 1 THEN
                            INSERT INTO tonkho_chinhanh (cuahang_id, sanpham_id, so_luong_ton, created_at, updated_at)
                            VALUES (p_cuahang_id, v_sp_id, v_qty, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE so_luong_ton = so_luong_ton + v_qty, updated_at = NOW();

                            SELECT so_luong_ton INTO v_stock_qty FROM tonkho_chinhanh WHERE cuahang_id = p_cuahang_id AND sanpham_id = v_sp_id LIMIT 1;

                            INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                            VALUES (p_cuahang_id, v_sp_id, 4, v_qty, v_stock_qty, v_ma_hd, NOW(), NOW());
                        END IF;

                        SET i = i + 1;
                    END WHILE;

                    UPDATE hoadon_nhap SET tong_tien = v_tong_tien WHERE id = p_hoadon_id;
                END IF;
            END
        ");

        // 10. sp_create_bien_ban_huy
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_bien_ban_huy");
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
                DECLARE v_don_gia DECIMAL(12,2);
                DECLARE v_thanh_tien DECIMAL(15,2);
                DECLARE v_tong_gia_tri DECIMAL(15,2) DEFAULT 0;
                DECLARE v_ma_bb VARCHAR(50);
                DECLARE v_sig_signed TINYINT DEFAULT 0;
                DECLARE v_new_batch_qty INT;
                DECLARE v_batch_status TINYINT;
                DECLARE v_stock_qty INT;

                SET p_bienban_id = NULL;
                SET p_ma_bien_ban = NULL;
                SET p_error = NULL;

                SET n = JSON_LENGTH(p_items_json);

                IF n IS NULL OR n = 0 THEN
                    SET p_error = 'Chi tiết sản phẩm hủy không được rỗng';
                ELSE
                    SET v_ma_bb = CONCAT('BBH-', UPPER(SUBSTRING(MD5(RAND()), 1, 10)));
                    SET v_sig_signed = IF(p_auto_sign_all = 1, 1, 0);

                    INSERT INTO bien_ban_huy (
                        ma_bien_ban, cuahang_id, nguoi_lap_id, ngay_lap, ly_do, tong_gia_tri_huy, trang_thai, created_at, updated_at
                    ) VALUES (
                        v_ma_bb, p_cuahang_id, p_nguoi_lap_id, NOW(), p_ly_do, 0, v_sig_signed, NOW(), NOW()
                    );

                    SET p_bienban_id = LAST_INSERT_ID();
                    SET p_ma_bien_ban = v_ma_bb;

                    INSERT INTO chu_ky_so (ma_chu_ky, loai_tai_lieu, tai_lieu_id, vai_tro_ky, nguoi_ky_id, ten_nguoi_ky, digital_signature_hash, public_key, trang_thai, ngay_ky, created_at, updated_at)
                    VALUES 
                    (CONCAT('SIG-HUY-', p_bienban_id, '-R1-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 2, p_bienban_id, 1, IF(v_sig_signed, p_nguoi_lap_id, NULL), 'Kế toán kiểm soát tiêu hủy', IF(v_sig_signed, SHA2(CONCAT('BBH_', p_bienban_id, '_R1_', UNIX_TIMESTAMP()), 256), NULL), IF(v_sig_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_sig_signed, IF(v_sig_signed, NOW(), NULL), NOW(), NOW()),
                    (CONCAT('SIG-HUY-', p_bienban_id, '-R2-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))), 2, p_bienban_id, 2, IF(v_sig_signed, p_nguoi_lap_id, NULL), 'Nhân viên quản lý kho tiêu hủy', IF(v_sig_signed, SHA2(CONCAT('BBH_', p_bienban_id, '_R2_', UNIX_TIMESTAMP()), 256), NULL), IF(v_sig_signed, 'SYSTEM_RSA_PUBLIC_KEY', NULL), v_sig_signed, IF(v_sig_signed, NOW(), NULL), NOW(), NOW());

                    WHILE i < n DO
                        SET v_lo_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].lohang_id'))) AS UNSIGNED);
                        SET v_qty_destroy = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].so_luong_huy'))) AS UNSIGNED);

                        SELECT sanpham_id, so_luong_con, gia_nhap INTO v_sp_id, v_qty_con, v_don_gia
                        FROM lo_hang WHERE id = v_lo_id LIMIT 1;

                        IF v_qty_destroy > v_qty_con THEN
                            SET v_qty_destroy = v_qty_con;
                        END IF;

                        SET v_thanh_tien = v_qty_destroy * v_don_gia;
                        SET v_tong_gia_tri = v_tong_gia_tri + v_thanh_tien;

                        INSERT INTO chitiet_bien_ban_huy (
                            bien_ban_huy_id, lohang_id, sanpham_id, so_luong_huy, don_gia, thanh_tien
                        ) VALUES (
                            p_bienban_id, v_lo_id, v_sp_id, v_qty_destroy, v_don_gia, v_thanh_tien
                        );

                        IF p_auto_sign_all = 1 THEN
                            SET v_new_batch_qty = GREATEST(0, v_qty_con - v_qty_destroy);
                            SET v_batch_status = IF(v_new_batch_qty = 0, 3, 2);

                            UPDATE lo_hang SET so_luong_con = v_new_batch_qty, trang_thai = v_batch_status, updated_at = NOW() WHERE id = v_lo_id;

                            UPDATE tonkho_chinhanh SET so_luong_ton = GREATEST(0, so_luong_ton - v_qty_destroy), updated_at = NOW()
                            WHERE cuahang_id = p_cuahang_id AND sanpham_id = v_sp_id;

                            SELECT COALESCE(so_luong_ton, 0) INTO v_stock_qty FROM tonkho_chinhanh WHERE cuahang_id = p_cuahang_id AND sanpham_id = v_sp_id LIMIT 1;

                            INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                            VALUES (p_cuahang_id, v_sp_id, 5, -v_qty_destroy, v_stock_qty, v_ma_bb, NOW(), NOW());
                        END IF;

                        SET i = i + 1;
                    END WHILE;

                    UPDATE bien_ban_huy SET tong_gia_tri_huy = v_tong_gia_tri WHERE id = p_bienban_id;
                END IF;
            END
        ");

        // 11. sp_sign_role
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_sign_role");
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
                DECLARE v_unsigned_count INT DEFAULT 0;
                DECLARE v_cuahang_id INT;
                DECLARE v_ma_hd VARCHAR(50);
                DECLARE v_ma_bb VARCHAR(50);
                DECLARE done INT DEFAULT FALSE;

                DECLARE c_sp_id INT;
                DECLARE c_qty INT;
                DECLARE c_stock_qty INT;

                DECLARE c_lo_id BIGINT;
                DECLARE c_qty_destroy INT;
                DECLARE c_batch_qty INT;
                DECLARE c_new_batch_qty INT;

                DECLARE cur1 CURSOR FOR 
                    SELECT sanpham_id, so_luong FROM chitiet_hoadon_nhap WHERE hoadon_nhap_id = v_tai_lieu_id;

                DECLARE cur2 CURSOR FOR 
                    SELECT lohang_id, sanpham_id, so_luong_huy FROM chitiet_bien_ban_huy WHERE bien_ban_huy_id = v_tai_lieu_id;

                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

                SET p_is_fully_signed = 0;
                SET p_error = NULL;

                SELECT trang_thai, loai_tai_lieu, tai_lieu_id, vai_tro_ky
                INTO v_status, v_loai_tai_lieu, v_tai_lieu_id, v_vai_tro_ky
                FROM chu_ky_so WHERE id = p_chu_ky_so_id LIMIT 1;

                IF v_status IS NULL THEN
                    SET p_error = 'Không tìm thấy chữ ký số';
                ELSEIF v_status = 1 THEN
                    SET p_error = 'Vai trò này đã được ký xác nhận trước đó';
                ELSE
                    SET v_sig_hash = SHA2(CONCAT(p_raw_payload, '_ROLE_', v_vai_tro_ky, '_', UNIX_TIMESTAMP()), 256);

                    UPDATE chu_ky_so
                    SET nguoi_ky_id = p_nguoi_ky_id,
                        digital_signature_hash = v_sig_hash,
                        public_key = COALESCE(p_public_key, 'SYSTEM_RSA_PUBLIC_KEY'),
                        trang_thai = 1,
                        ngay_ky = NOW(),
                        ghi_chu = COALESCE(p_ghi_chu, 'Đã ký xác nhận thành công'),
                        updated_at = NOW()
                    WHERE id = p_chu_ky_so_id;

                    SELECT COUNT(*) INTO v_unsigned_count
                    FROM chu_ky_so
                    WHERE loai_tai_lieu = v_loai_tai_lieu AND tai_lieu_id = v_tai_lieu_id AND trang_thai != 1;

                    IF v_unsigned_count = 0 THEN
                        SET p_is_fully_signed = 1;

                        IF v_loai_tai_lieu = 1 THEN
                            SELECT cuahang_id, ma_hoadon_nhap INTO v_cuahang_id, v_ma_hd FROM hoadon_nhap WHERE id = v_tai_lieu_id LIMIT 1;
                            UPDATE hoadon_nhap SET trang_thai = 1, updated_at = NOW() WHERE id = v_tai_lieu_id;

                            OPEN cur1;
                            read_cur1: LOOP
                                FETCH cur1 INTO c_sp_id, c_qty;
                                IF done THEN LEAVE read_cur1; END IF;

                                INSERT INTO tonkho_chinhanh (cuahang_id, sanpham_id, so_luong_ton, created_at, updated_at)
                                VALUES (v_cuahang_id, c_sp_id, c_qty, NOW(), NOW())
                                ON DUPLICATE KEY UPDATE so_luong_ton = so_luong_ton + c_qty, updated_at = NOW();

                                SELECT so_luong_ton INTO c_stock_qty FROM tonkho_chinhanh WHERE cuahang_id = v_cuahang_id AND sanpham_id = c_sp_id LIMIT 1;

                                INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                                VALUES (v_cuahang_id, c_sp_id, 4, c_qty, c_stock_qty, v_ma_hd, NOW(), NOW());
                            END LOOP;
                            CLOSE cur1;

                        ELSEIF v_loai_tai_lieu = 2 THEN
                            SELECT cuahang_id, ma_bien_ban INTO v_cuahang_id, v_ma_bb FROM bien_ban_huy WHERE id = v_tai_lieu_id LIMIT 1;
                            UPDATE bien_ban_huy SET trang_thai = 1, updated_at = NOW() WHERE id = v_tai_lieu_id;

                            OPEN cur2;
                            read_cur2: LOOP
                                FETCH cur2 INTO c_lo_id, c_sp_id, c_qty_destroy;
                                IF done THEN LEAVE read_cur2; END IF;

                                SELECT COALESCE(so_luong_con, 0) INTO c_batch_qty FROM lo_hang WHERE id = c_lo_id LIMIT 1;
                                SET c_new_batch_qty = GREATEST(0, c_batch_qty - c_qty_destroy);

                                UPDATE lo_hang SET so_luong_con = c_new_batch_qty, trang_thai = IF(c_new_batch_qty = 0, 3, 2), updated_at = NOW() WHERE id = c_lo_id;

                                UPDATE tonkho_chinhanh SET so_luong_ton = GREATEST(0, so_luong_ton - c_qty_destroy), updated_at = NOW() WHERE cuahang_id = v_cuahang_id AND sanpham_id = c_sp_id;

                                SELECT COALESCE(so_luong_ton, 0) INTO c_stock_qty FROM tonkho_chinhanh WHERE cuahang_id = v_cuahang_id AND sanpham_id = c_sp_id LIMIT 1;

                                INSERT INTO nhat_ky_kho (cuahang_id, sanpham_id, loai_bien_dong, so_luong_thay_doi, so_luong_ton_sau, ma_tham_chieu, created_at, updated_at)
                                VALUES (v_cuahang_id, c_sp_id, 5, -c_qty_destroy, c_stock_qty, v_ma_bb, NOW(), NOW());
                            END LOOP;
                            CLOSE cur2;
                        END IF;
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_open_pos_shift");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_close_pos_shift");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_pos_checkout");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_checkout_online");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_cancel_order");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_handle_ghn_webhook");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_transfer_request");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_update_transfer_status");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_hoadon_nhap");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_bien_ban_huy");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_sign_role");
    }
};
