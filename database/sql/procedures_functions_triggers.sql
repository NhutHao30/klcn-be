-- =========================================================================================
-- HỆ THỐNG QUẢN LÝ TIỆM BÁNH GIVRAL BAKERY
-- TỔNG HỢP CÁC STORED PROCEDURE, FUNCTION VÀ TRIGGER CỐT LÕI (DÙNG ĐỂ BÁO CÁO & CHỤP HÌNH)
-- Database: Laravel_DB (MySQL 8.0)
-- =========================================================================================

USE `Laravel_DB`;

DELIMITER $$

-- =========================================================================================
-- PHẦN 1: CÁC STORED PROCEDURE CHÍNH (THỦ TỤC LƯU TRỮ)
-- =========================================================================================

-- -----------------------------------------------------------------------------------------
-- 1.1. Thủ tục Đặt hàng Online (sp_checkout_online)
-- Mô tả: Kiểm tra số lượng tồn kho từng bánh tại chi nhánh, áp dụng voucher giảm giá,
--        tạo hóa đơn đặt hàng và trừ tồn kho tự động trong cùng một Transaction nguyên tử.
-- -----------------------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `sp_checkout_online`$$
CREATE PROCEDURE `sp_checkout_online`(
    IN p_nguoidung_id BIGINT,
    IN p_cuahang_id INT,
    IN p_diachi_giaohang TEXT,
    IN p_phuongthuc_tt INT,
    IN p_ma_voucher VARCHAR(50),
    IN p_ghi_chu TEXT,
    IN p_items_json JSON,
    OUT p_donhang_id BIGINT,
    OUT p_ma_donhang VARCHAR(50),
    OUT p_error VARCHAR(255)
)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE n INT DEFAULT 0;
    DECLARE v_sp_id INT;
    DECLARE v_qty INT;
    DECLARE v_price DECIMAL(15,2);
    DECLARE v_stock INT;
    DECLARE v_subtotal DECIMAL(15,2) DEFAULT 0;
    DECLARE v_discount DECIMAL(15,2) DEFAULT 0;
    DECLARE v_shipping DECIMAL(15,2) DEFAULT 25000;
    DECLARE v_total DECIMAL(15,2) DEFAULT 0;
    DECLARE v_ma_dh VARCHAR(50);
    DECLARE v_voucher_id BIGINT;
    DECLARE v_giam_phantram INT;
    DECLARE v_giam_toida DECIMAL(15,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_error = 'Lỗi hệ thống khi xử lý đặt hàng online (Transaction Rollback)';
    END;

    START TRANSACTION;
    SET p_donhang_id = NULL;
    SET p_ma_donhang = NULL;
    SET p_error = NULL;
    SET n = JSON_LENGTH(p_items_json);

    IF n IS NULL OR n = 0 THEN
        SET p_error = 'Giỏ hàng không có sản phẩm nào!';
        ROLLBACK;
    ELSE
        -- 1. Kiểm tra tồn kho từng sản phẩm tại chi nhánh
        WHILE i < n DO
            SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].id'))) AS UNSIGNED);
            SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].quantity'))) AS UNSIGNED);

            SELECT COALESCE(soLuongTon, 0) INTO v_stock
            FROM tonKhoChiNhanh
            WHERE cuaHangId = p_cuahang_id AND sanPhamId = v_sp_id
            FOR UPDATE;

            IF v_stock IS NULL OR v_stock < v_qty THEN
                SET p_error = CONCAT('Sản phẩm mã #', v_sp_id, ' không đủ tồn kho tại chi nhánh này!');
            END IF;

            SELECT COALESCE(giaBan, 0) INTO v_price FROM sanPham WHERE id = v_sp_id;
            SET v_subtotal = v_subtotal + (v_price * v_qty);
            SET i = i + 1;
        END WHILE;

        IF p_error IS NOT NULL THEN
            ROLLBACK;
        ELSE
            -- 2. Áp dụng mã Voucher giảm giá nếu có
            IF p_ma_voucher IS NOT NULL AND p_ma_voucher != '' THEN
                SELECT id, PHAN_TRAM_GIAM, GIAM_TOI_DA INTO v_voucher_id, v_giam_phantram, v_giam_toida
                FROM voucher
                WHERE MA_VOUCHER = p_ma_voucher AND TRANG_THAI = 1 AND NGAY_BAT_DAU <= NOW() AND NGAY_KET_THUC >= NOW()
                LIMIT 1;

                IF v_voucher_id IS NOT NULL THEN
                    SET v_discount = (v_subtotal * v_giam_phantram) / 100;
                    IF v_giam_toida IS NOT NULL AND v_discount > v_giam_toida THEN
                        SET v_discount = v_giam_toida;
                    END IF;
                END IF;
            END IF;

            SET v_total = GREATEST(0, (v_subtotal + v_shipping) - v_discount);
            SET v_ma_dh = CONCAT('HD', UPPER(SUBSTRING(MD5(RAND()), 1, 8)));

            -- 3. Tạo đơn hàng mới
            INSERT INTO donHang (
                maDonHang, nguoidungId, cuaHangId, diaChiGiaoHang, loaiDonHang,
                phuongThucThanhToan, maVoucher, tongTienHang, phiVanChuyen, soTienGiam,
                tongThanhToan, trangThaiDonHang, trangThaiThanhToan, ghiChu, createdAt, updatedAt
            ) VALUES (
                v_ma_dh, p_nguoidung_id, p_cuahang_id, p_diachi_giaohang, 0,
                p_phuongthuc_tt, p_ma_voucher, v_subtotal, v_shipping, v_discount,
                v_total, 1, 0, p_ghi_chu, NOW(), NOW()
            );

            SET p_donhang_id = LAST_INSERT_ID();
            SET p_ma_donhang = v_ma_dh;

            -- 4. Thêm chi tiết đơn hàng & Trừ tồn kho chi nhánh
            SET i = 0;
            WHILE i < n DO
                SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].id'))) AS UNSIGNED);
                SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].quantity'))) AS UNSIGNED);
                SELECT COALESCE(giaBan, 0) INTO v_price FROM sanPham WHERE id = v_sp_id;

                INSERT INTO chiTietDonHang (donHangId, sanPhamId, soLuong, donGia, thanhTien, createdAt, updatedAt)
                VALUES (p_donhang_id, v_sp_id, v_qty, v_price, (v_qty * v_price), NOW(), NOW());

                UPDATE tonKhoChiNhanh
                SET soLuongTon = soLuongTon - v_qty, updatedAt = NOW()
                WHERE cuaHangId = p_cuahang_id AND sanPhamId = v_sp_id;

                SET i = i + 1;
            END WHILE;

            COMMIT;
        END IF;
    END IF;
END$$


-- -----------------------------------------------------------------------------------------
-- 1.2. Thủ tục Đóng ca POS & Đối soát chênh lệch két tiền mặt (sp_close_pos_shift)
-- Mô tả: Tự động gom doanh thu theo từng phương thức (Tiền mặt, VietQR, MoMo),
--        tính số tiền mặt lý thuyết trong két và xác định mức chênh lệch thừa/thiếu.
-- -----------------------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `sp_close_pos_shift`$$
CREATE PROCEDURE `sp_close_pos_shift`(
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

    -- Tìm ca làm việc đang mở của nhân viên
    SELECT id, cuaHangId, thoiGianMoCa, tienMatDauCa
    INTO v_shift_id, v_cuahang_id, v_mo_ca, v_tien_dau
    FROM posShifts
    WHERE nhanVienId = p_nhanvien_id AND trangThai = 0
    LIMIT 1;

    IF v_shift_id IS NULL THEN
        SET p_error = 'Không tìm thấy ca làm việc POS nào đang mở cho nhân viên này!';
    ELSE
        -- 1. Doanh thu tiền mặt tại quầy (phuongThucThanhToan = 0)
        SELECT COALESCE(SUM(tongThanhToan), 0) INTO v_rev_cash
        FROM donHang
        WHERE cuaHangId = v_cuahang_id AND loaiDonHang = 1
          AND phuongThucThanhToan = 0 AND trangThaiThanhToan = 1 AND createdAt >= v_mo_ca;

        -- 2. Doanh thu Chuyển khoản / VietQR (phuongThucThanhToan = 1)
        SELECT COALESCE(SUM(tongThanhToan), 0) INTO v_rev_bank
        FROM donHang
        WHERE cuaHangId = v_cuahang_id AND loaiDonHang = 1
          AND phuongThucThanhToan = 1 AND trangThaiThanhToan = 1 AND createdAt >= v_mo_ca;

        -- 3. Doanh thu Ví MoMo (phuongThucThanhToan = 2)
        SELECT COALESCE(SUM(tongThanhToan), 0) INTO v_rev_momo
        FROM donHang
        WHERE cuaHangId = v_cuahang_id AND loaiDonHang = 1
          AND phuongThucThanhToan = 2 AND trangThaiThanhToan = 1 AND createdAt >= v_mo_ca;

        SET v_rev_total = v_rev_cash + v_rev_bank + v_rev_momo;
        SET v_tien_sach = v_tien_dau + v_rev_cash;
        SET v_chenh_lech = p_tien_mat_thuc_te - v_tien_sach;

        -- Cập nhật ca làm việc: Chốt số liệu & đóng ca (trangThai = 1)
        UPDATE posShifts
        SET thoiGianDongCa = NOW(),
            doanhThuTienMat = v_rev_cash,
            doanhThuChuyenKhoan = v_rev_bank,
            doanhThuMomo = v_rev_momo,
            tongDoanhThu = v_rev_total,
            tienMatSach = v_tien_sach,
            tienMatThucTe = p_tien_mat_thuc_te,
            chenhLech = v_chenh_lech,
            trangThai = 1,
            updatedAt = NOW()
        WHERE id = v_shift_id;

        SET p_shift_id = v_shift_id;
    END IF;
END$$


-- -----------------------------------------------------------------------------------------
-- 1.3. Thủ tục Tạo Hóa Đơn Nhập Hàng & Lô Hàng Hạn Sử Dụng (sp_create_hoadon_nhap)
-- Mô tả: Nhập hàng từ nhà cung cấp, sinh mã lô hàng, kiểm soát NSX & HSD bánh,
--        cộng dồn tồn kho chi nhánh và khởi tạo 3 chữ ký số phân quyền (Kế toán, Kho, Quản lý).
-- -----------------------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `sp_create_hoadon_nhap`$$
CREATE PROCEDURE `sp_create_hoadon_nhap`(
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

    SET p_hoadon_id = NULL;
    SET p_ma_hoadon = NULL;
    SET p_error = NULL;
    SET n = JSON_LENGTH(p_items_json);

    IF n IS NULL OR n = 0 THEN
        SET p_error = 'Chi tiết sản phẩm nhập hàng không được rỗng!';
    ELSE
        SET v_manager_signed = IF(p_auto_sign_all = 1 OR (p_ky_quan_ly IS NOT NULL AND p_ky_quan_ly != ''), 1, 0);
        SET v_ma_hd = CONCAT('HDN-', UPPER(SUBSTRING(MD5(RAND()), 1, 10)));

        INSERT INTO hoaDonNhap (
            maHoaDonNhap, nhaCungCapId, cuaHangId, nguoiNhapId, ngayNhap,
            tongTien, ghiChu, trangThai, createdAt, updatedAt
        ) VALUES (
            v_ma_hd, p_nhacungcap_id, p_cuahang_id, p_nguoi_nhap_id, NOW(),
            0, p_ghi_chu, v_manager_signed, NOW(), NOW()
        );

        SET p_hoadon_id = LAST_INSERT_ID();
        SET p_ma_hoadon = v_ma_hd;

        -- Khởi tạo 3 vị trí ký điện tử: 1 = Kế toán, 2 = Thủ kho, 3 = Quản lý chi nhánh
        INSERT INTO chuKySo (maChuKy, loaiTaiLieu, taiLieuId, vaiTroKy, nguoiKyId, tenNguoiKy, trangThai, ngayKy, createdAt, updatedAt)
        VALUES
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R1'), 1, p_hoadon_id, 1, NULL, 'Nhân viên kế toán', 0, NULL, NOW(), NOW()),
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R2'), 1, p_hoadon_id, 2, NULL, 'Nhân viên kho', 0, NULL, NOW(), NOW()),
        (CONCAT('SIG-HDN-', p_hoadon_id, '-R3'), 1, p_hoadon_id, 3, IF(v_manager_signed = 1, p_nguoi_nhap_id, NULL), 'Quản lý chi nhánh', v_manager_signed, IF(v_manager_signed = 1, NOW(), NULL), NOW(), NOW());

        -- Lặp qua từng sản phẩm để tạo Lô hàng & Chi tiết nhập
        WHILE i < n DO
            SET v_sp_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].sanPhamId'))) AS UNSIGNED);
            SET v_qty = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].soLuong'))) AS UNSIGNED);
            SET v_don_gia = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].donGiaNhap'))) AS DECIMAL(12,2));
            SET v_nsx = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].ngaySanXuat'))), 'null');
            SET v_hsd = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', i, '].hanSuDung'))), 'null');

            SET v_thanh_tien = v_qty * v_don_gia;
            SET v_tong_tien = v_tong_tien + v_thanh_tien;
            SET v_ma_lo = CONCAT('LO-', p_cuahang_id, '-', v_sp_id, '-', DATE_FORMAT(NOW(), '%y%m%d%H%i%s'), '-', (i + 1));

            -- Tạo lô hàng (theo dõi hạn sử dụng bánh)
            INSERT INTO loHang (maLoHang, sanPhamId, nhaCungCapId, cuaHangId, soLuongNhap, soLuongCon, donGiaNhap, ngaySanXuat, hanSuDung, trangThai, createdAt, updatedAt)
            VALUES (v_ma_lo, v_sp_id, p_nhacungcap_id, p_cuahang_id, v_qty, v_qty, v_don_gia, v_nsx, v_hsd, 1, NOW(), NOW());
            SET v_lo_id = LAST_INSERT_ID();

            -- Tạo chi tiết hóa đơn nhập
            INSERT INTO chiTietHoaDonNhap (hoaDonNhapId, sanPhamId, loHangId, soLuongNhap, donGiaNhap, thanhTien, createdAt, updatedAt)
            VALUES (p_hoadon_id, v_sp_id, v_lo_id, v_qty, v_don_gia, v_thanh_tien, NOW(), NOW());

            -- Cộng tồn kho vào chi nhánh
            INSERT INTO tonKhoChiNhanh (cuaHangId, sanPhamId, soLuongTon, createdAt, updatedAt)
            VALUES (p_cuahang_id, v_sp_id, v_qty, NOW(), NOW())
            ON DUPLICATE KEY UPDATE soLuongTon = soLuongTon + v_qty, updatedAt = NOW();

            SET i = i + 1;
        END WHILE;

        -- Cập nhật tổng tiền hóa đơn nhập
        UPDATE hoaDonNhap SET tongTien = v_tong_tien, updatedAt = NOW() WHERE id = p_hoadon_id;
    END IF;
END$$


-- =========================================================================================
-- PHẦN 2: CÁC FUNCTION (HÀM TÍNH TOÁN CSDL)
-- =========================================================================================

-- -----------------------------------------------------------------------------------------
-- 2.1. Hàm Kiểm tra Chuyển đổi Trạng thái Đơn Hàng (fn_can_transition)
-- Mô tả: Kiểm tra tính hợp lệ của việc chuyển đổi trạng thái đơn hàng (State Machine).
-- -----------------------------------------------------------------------------------------
DROP FUNCTION IF EXISTS `fn_can_transition`$$
CREATE FUNCTION `fn_can_transition`(current_status VARCHAR(50), new_status VARCHAR(50))
RETURNS TINYINT(1)
DETERMINISTIC
BEGIN
    IF current_status = new_status THEN
        RETURN TRUE;
    END IF;

    -- Chờ xác nhận (1) -> Đang chuẩn bị (2), Đã hủy (0)
    IF current_status IN ('1', 'Chờ xác nhận', 'Chờ xử lý') AND new_status IN ('2', 'Đang chuẩn bị', '0', 'Đã hủy') THEN
        RETURN TRUE;
    -- Đang chuẩn bị (2) -> Chờ lấy hàng (7), Đang giao (3), Đã hủy (0)
    ELSEIF current_status IN ('2', 'Đang chuẩn bị') AND new_status IN ('7', 'Chờ lấy hàng', '3', 'Đang giao', '0', 'Đã hủy') THEN
        RETURN TRUE;
    -- Chờ lấy hàng (7) -> Đang giao (3)
    ELSEIF current_status IN ('7', 'Chờ lấy hàng') AND new_status IN ('3', 'Đang giao') THEN
        RETURN TRUE;
    -- Đang giao (3) -> Hoàn thành (4/5), Thất bại / Hoàn trả
    ELSEIF current_status IN ('3', 'Đang giao') AND new_status IN ('4', '5', 'Đã hoàn thành', 'Đã giao') THEN
        RETURN TRUE;
    -- Đã giao (4) -> Yêu cầu hoàn trả (8)
    ELSEIF current_status IN ('4', '5', 'Đã hoàn thành', 'Đã giao') AND new_status IN ('8', 'Yêu cầu hoàn trả') THEN
        RETURN TRUE;
    -- Yêu cầu hoàn trả (8) -> Đã hoàn hàng (6) hoặc Khôi phục Đã giao (4)
    ELSEIF current_status IN ('8', 'Yêu cầu hoàn trả') AND new_status IN ('6', 'Đã hoàn hàng', '4', 'Đã giao') THEN
        RETURN TRUE;
    END IF;

    RETURN FALSE;
END$$


-- -----------------------------------------------------------------------------------------
-- 2.2. Hàm Tính Số Tiền Giảm Giá Voucher (fn_calculate_voucher_discount)
-- Mô tả: Nhận vào tổng tiền hàng và mã voucher, tự động tính số tiền được giảm theo
--        % chiết khấu và chặn trần số tiền giảm tối đa (GIAM_TOI_DA).
-- -----------------------------------------------------------------------------------------
DROP FUNCTION IF EXISTS `fn_calculate_voucher_discount`$$
CREATE FUNCTION `fn_calculate_voucher_discount`(p_subtotal DECIMAL(15,2), p_ma_voucher VARCHAR(50))
RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_percent INT DEFAULT 0;
    DECLARE v_max_discount DECIMAL(15,2) DEFAULT NULL;
    DECLARE v_min_order DECIMAL(15,2) DEFAULT 0;
    DECLARE v_discount DECIMAL(15,2) DEFAULT 0;

    SELECT PHAN_TRAM_GIAM, GIAM_TOI_DA, COALESCE(DON_TOI_THIEU, 0)
    INTO v_percent, v_max_discount, v_min_order
    FROM voucher
    WHERE MA_VOUCHER = p_ma_voucher AND TRANG_THAI = 1
      AND NGAY_BAT_DAU <= NOW() AND NGAY_KET_THUC >= NOW()
    LIMIT 1;

    -- Kiểm tra điều kiện đơn hàng tối thiểu
    IF v_percent > 0 AND p_subtotal >= v_min_order THEN
        SET v_discount = (p_subtotal * v_percent) / 100;
        IF v_max_discount IS NOT NULL AND v_discount > v_max_discount THEN
            SET v_discount = v_max_discount;
        END IF;
    END IF;

    RETURN v_discount;
END$$


-- -----------------------------------------------------------------------------------------
-- 2.3. Hàm Phân Hạng Thành Viên Khách Hàng (fn_get_customer_rank)
-- Mô tả: Dựa vào tổng điểm tích lũy hoặc chi tiêu của khách để trả về danh hiệu:
--        Đồng, Bạc, Vàng, hoặc Kim Cương (VIP).
-- -----------------------------------------------------------------------------------------
DROP FUNCTION IF EXISTS `fn_get_customer_rank`$$
CREATE FUNCTION `fn_get_customer_rank`(p_diem_tich_luy INT)
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    IF p_diem_tich_luy >= 10000 THEN
        RETURN 'Kim Cương (Diamond VIP)';
    ELSEIF p_diem_tich_luy >= 5000 THEN
        RETURN 'Vàng (Gold VIP)';
    ELSEIF p_diem_tich_luy >= 1000 THEN
        RETURN 'Bạc (Silver)';
    ELSE
        RETURN 'Đồng (Member)';
    END IF;
END$$


-- =========================================================================================
-- PHẦN 3: CÁC TRIGGER CHÍNH (BỘ KÍCH HOẠT TỰ ĐỘNG)
-- =========================================================================================

-- -----------------------------------------------------------------------------------------
-- 3.1. Trigger Ghi Lịch Sử Khi Trạng Thái Đơn Hàng Thay Đổi (trg_after_update_donhang_status)
-- Mô tả: Bất cứ khi nào trạng thái đơn hàng (trangThaiDonHang) thay đổi, tự động chèn một dòng
--        vào bảng lịch sử lichSuTrangThaiDonHang để lưu vết kiểm toán (Audit Trail).
-- -----------------------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_after_update_donhang_status`$$
CREATE TRIGGER `trg_after_update_donhang_status`
AFTER UPDATE ON `donHang`
FOR EACH ROW
BEGIN
    IF OLD.trangThaiDonHang != NEW.trangThaiDonHang THEN
        INSERT INTO lichSuTrangThaiDonHang (
            donHangId, trangThaiCu, trangThaiMoi, ghnStatusCode,
            moTaChiTiet, nguonCapNhat, createdAt, updatedAt
        ) VALUES (
            NEW.id,
            OLD.trangThaiDonHang,
            NEW.trangThaiDonHang,
            COALESCE(NEW.maVanDonGhn, 'system_update'),
            CONCAT('Trạng thái chuyển từ [', OLD.trangThaiDonHang, '] sang [', NEW.trangThaiDonHang, ']'),
            1, -- 1: Hệ thống tự động
            NOW(),
            NOW()
        );
    END IF;
END$$


-- -----------------------------------------------------------------------------------------
-- 3.2. Trigger Khóa Cập Nhật Đơn Hàng Đã Hủy Hoặc Hoàn Thành (trg_before_update_donhang_lock)
-- Mô tả: Bảo vệ tính toàn vẹn dữ liệu, chặn sửa đổi đơn giá / tổng tiền khi đơn đã hoàn tất hoặc đã hủy.
-- -----------------------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_before_update_donhang_lock`$$
CREATE TRIGGER `trg_before_update_donhang_lock`
BEFORE UPDATE ON `donHang`
FOR EACH ROW
BEGIN
    -- Nếu đơn hàng đã hoàn tất (4, 5) hoặc đã hủy (0), không cho phép sửa đổi số tiền
    IF OLD.trangThaiDonHang IN (0, 4, 5) AND (OLD.tongThanhToan != NEW.tongThanhToan OR OLD.tongTienHang != NEW.tongTienHang) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Lỗi nghiệp vụ: Không được phép chỉnh sửa tổng tiền của đơn hàng đã Hoàn thành hoặc Đã hủy!';
    END IF;
END$$


-- -----------------------------------------------------------------------------------------
-- 3.3. Trigger Tự Động Tích Lũy Điểm Thưởng Cho Khách Hàng (trg_after_order_completed_loyalty)
-- Mô tả: Khi đơn hàng được chuyển sang trạng thái Đã giao / Hoàn thành (4/5) và đã thanh toán,
--        tự động tích lũy 1% giá trị đơn hàng thành điểm thưởng cho khách hàng (nguoidung).
-- -----------------------------------------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_after_order_completed_loyalty`$$
CREATE TRIGGER `trg_after_order_completed_loyalty`
AFTER UPDATE ON `donHang`
FOR EACH ROW
BEGIN
    DECLARE v_diem_cong INT DEFAULT 0;

    -- Khi đơn chuyển sang hoàn thành (4 hoặc 5) và khách có tài khoản
    IF (OLD.trangThaiDonHang NOT IN (4, 5) AND NEW.trangThaiDonHang IN (4, 5)) AND NEW.nguoidungId IS NOT NULL THEN
        -- 10.000 VNĐ tương đương 1 điểm thưởng
        SET v_diem_cong = FLOOR(NEW.tongThanhToan / 10000);

        IF v_diem_cong > 0 THEN
            UPDATE nguoidung
            SET diemTichLuy = COALESCE(diemTichLuy, 0) + v_diem_cong,
                tongChiTieu = COALESCE(tongChiTieu, 0) + NEW.tongThanhToan,
                updatedAt = NOW()
            WHERE id = NEW.nguoidungId;
        END IF;
    END IF;
END$$

DELIMITER ;

