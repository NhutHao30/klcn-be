<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\NhatKyHeThongController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\DatHangTruocController;
use App\Http\Controllers\Api\GhnController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\PageViewController;

Broadcast::routes(['middleware' => ['auth:api']]);
// ==========================================
// ==========================================
// 🔓 API CÔNG KHAI
// ==========================================
Route::get('/proxy-image', [\App\Http\Controllers\Api\ImageProxyController::class, 'show']);
Route::post('/track-page-view', [PageViewController::class, 'store']);

// GHN Webhook & Simulation Routes
Route::any('/ghn/webhook', [GhnController::class, 'webhook']);
Route::any('/ghn/simulate-webhook', [GhnController::class, 'webhook']);
Route::post('/hoa-don/{id}/xac-nhan-hoan-hang', [\App\Http\Controllers\Api\OrderController::class, 'confirmReturnInspection']);

// Payment Webhook (SePay, Casso, PayOS, Banks) & Live Status Polling
Route::any('/payment/webhook', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'webhook']);
Route::get('/payment/check-status/{orderCode}', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'checkStatus']);
Route::post('/payment/simulate-success', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'simulatePayment']);

Route::middleware(['log.activity'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register/send-otp', [AuthController::class, 'sendRegisterOtp']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Google OAuth Routes
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
    Route::get('/google-status', [GoogleAuthController::class, 'googleStatus']);
    Route::post('/google-register', [GoogleAuthController::class, 'googleRegister']);

    Route::get('/setup-vouchers', function () {
        $logs = [];

        // 1. Table khuyenmai
        if (!\Illuminate\Support\Facades\Schema::hasTable('khuyenmai')) {
            \Illuminate\Support\Facades\Schema::create('khuyenmai', function ($table) {
                $table->string('MA_VOUCHER', 50)->primary();
                $table->string('TEN_VOUCHER', 255);
                $table->string('LOAI_GIAM', 20)->default('PHAN_TRAM'); // PHAN_TRAM hoac TIEN_MAT
                $table->integer('GIA_TRI');
                $table->integer('DON_TOI_THIEU')->default(0);
                $table->integer('GIAM_TOI_DA')->nullable();
                $table->integer('SO_LUONG_GIOI_HAN')->default(0);
                $table->integer('SO_LUONG_DA_DUNG')->default(0);
                $table->dateTime('NGAY_BAT_DAU');
                $table->dateTime('NGAY_KET_THUC');
                $table->boolean('TRANG_THAI')->default(true);
                $table->timestamps();
            });
            $logs[] = 'Created khuyenmai table';
        }

        // 2. Table khuyenmai_khachhang
        if (!\Illuminate\Support\Facades\Schema::hasTable('khuyenmai_khachhang')) {
            \Illuminate\Support\Facades\Schema::create('khuyenmai_khachhang', function ($table) {
                $table->id();
                $table->string('MA_VOUCHER', 50);
                $table->string('MAKH', 50);
                $table->string('MAHD', 50);
                $table->dateTime('NGAY_SU_DUNG');
                $table->timestamps();

                // Note: Not setting strict foreign keys here to prevent restriction issues
            });
            $logs[] = 'Created khuyenmai_khachhang table';
        }

        // 3. Update hdban table
        if (!\Illuminate\Support\Facades\Schema::hasColumn('hdban', 'MA_VOUCHER')) {
            \Illuminate\Support\Facades\Schema::table('hdban', function ($table) {
                $table->string('MA_VOUCHER', 50)->nullable();
                $table->integer('SOTIEN_GIAM_VOUCHER')->default(0);
            });
            $logs[] = 'Updated hdban with voucher columns';
        }

        return count($logs) > 0 ? implode(', ', $logs) : 'Voucher tables already exist.';
    });

    Route::get('/setup-reviews', function () {
        $logs = [];
        if (!\Illuminate\Support\Facades\Schema::hasTable('danhgia_sanpham')) {
            \Illuminate\Support\Facades\Schema::create('danhgia_sanpham', function ($table) {
                $table->id('MADG');
                $table->string('MASP', 50);
                $table->string('MAKH', 50);
                $table->string('MAHD', 50);
                $table->integer('SO_SAO');
                $table->text('NOI_DUNG')->nullable();
                $table->string('HINH_ANH', 500)->nullable();
                $table->dateTime('NGAY_DG');
                $table->boolean('TRANG_THAI')->default(true);
                $table->timestamps();
            });
            $logs[] = 'Created danhgia_sanpham table';
        }
        return count($logs) > 0 ? implode(', ', $logs) : 'Review table already exists.';
    });

});

// Giao Hàng Nhanh (GHN) API Proxy
Route::get('/ghn/provinces', [\App\Http\Controllers\Api\GhnController::class, 'getProvinces']);
Route::get('/ghn/districts', [\App\Http\Controllers\Api\GhnController::class, 'getDistricts']);
Route::get('/ghn/wards', [\App\Http\Controllers\Api\GhnController::class, 'getWards']);
Route::get('/ghn/wards-by-province', [\App\Http\Controllers\Api\GhnController::class, 'getWardsByProvince']);
Route::post('/ghn/fee', [\App\Http\Controllers\Api\GhnController::class, 'calculateFee']);
Route::post('/ghn/webhook', [\App\Http\Controllers\Api\GhnController::class, 'webhook']);
Route::get('/ghn/tracking/{orderCode}', [\App\Http\Controllers\Api\GhnController::class, 'getTrackingDetail']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/best-sellers', [ProductController::class, 'getBestSellers']);
Route::get('/products/homepage', [ProductController::class, 'getHomepageProducts']);

// News Public
Route::get('/news', [\App\Http\Controllers\Api\NewsController::class, 'index']);
Route::get('/news/{id}', [\App\Http\Controllers\Api\NewsController::class, 'show']);
Route::get('/news/{id}/comments', [\App\Http\Controllers\Api\NewsController::class, 'getComments']);

// Stores Public
Route::get('/stores', [\App\Http\Controllers\Api\StoreController::class, 'publicIndex']);

// Contact Public
Route::post('/contact', [\App\Http\Controllers\Api\ContactController::class, 'sendContactEmail']);

// Voucher Customer
Route::post('/vouchers/apply', [\App\Http\Controllers\Api\VoucherController::class, 'applyVoucher']);
Route::post('/vouchers/suggest-for-order', [\App\Http\Controllers\Api\VoucherController::class, 'suggestForOrder']);
Route::get('/vouchers/suggest-for-order', [\App\Http\Controllers\Api\VoucherController::class, 'suggestForOrder']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/price-history', [ProductController::class, 'getPriceHistory']);
Route::get('/products/{id}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'getProductReviews']);
// ==========================================
// 🔒 API BẢO MẬT (ĐÃ ĐĂNG NHẬP)
// ==========================================
Route::middleware(['auth:api', 'log.activity'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/chu-ky', [\App\Http\Controllers\Api\SignatureController::class, 'updateProfileSignature']);
    Route::get('/chu-ky/danh-sach-cho-ky', [\App\Http\Controllers\Api\SignatureController::class, 'getPendingSignatures']);
    Route::post('/chu-ky/xac-nhan', [\App\Http\Controllers\Api\SignatureController::class, 'signDocument']);

    // Đánh giá sản phẩm (Review)
    Route::post('/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'storeReview']);
    Route::post('/reviews/{id}/comments', [\App\Http\Controllers\Api\ReviewController::class, 'commentReview']);
    Route::post('/payment-receipt/verify', [\App\Http\Controllers\Api\PaymentReceiptOcrController::class, 'verify']);

    // Wishlist (Sản phẩm yêu thích)
    Route::get('/wishlist', [\App\Http\Controllers\Api\WishlistController::class, 'index']);
    Route::post('/wishlist', [\App\Http\Controllers\Api\WishlistController::class, 'store']);
    Route::delete('/wishlist/{masp}', [\App\Http\Controllers\Api\WishlistController::class, 'destroy']);
    Route::get('/wishlist/check/{masp}', [\App\Http\Controllers\Api\WishlistController::class, 'check']);

    // Upload minh chứng thanh toán (Khách hàng & Nhân viên)
    Route::post('/orders/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);
    Route::post('/hoa-don/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);
    Route::post('/my-orders/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    Route::put('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

    // Bình luận tin tức
    Route::post('/news/{id}/comments', [\App\Http\Controllers\Api\NewsController::class, 'postComment']);

    // ==========================================
    // 💬 API CHAT REALTIME (Dành cho CẢ NHÂN VIÊN và KHÁCH HÀNG)
    // ==========================================
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/chat/conversations', [\App\Http\Controllers\Api\ChatController::class, 'getConversations']);
        Route::post('/chat/conversations', [\App\Http\Controllers\Api\ChatController::class, 'startConversation']);
        Route::get('/chat/conversations/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'getMessages']);
        Route::post('/chat/conversations/{id}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendMessage']);
        Route::delete('/chat/conversations/{id}/messages/{msgId}', [\App\Http\Controllers\Api\ChatController::class, 'recallMessage']);

        // Chat Nội Bộ
        Route::get('/chat/internal/{channel}/messages', [\App\Http\Controllers\Api\ChatController::class, 'getInternalMessages']);
        Route::post('/chat/internal/{channel}/messages', [\App\Http\Controllers\Api\ChatController::class, 'sendInternalMessage']);
        Route::delete('/chat/internal/{channel}/messages/{msgId}', [\App\Http\Controllers\Api\ChatController::class, 'recallInternalMessage']);

        // Chat Trạng thái CSKH Trực tuyến / Ngoại tuyến
        Route::get('/chat/staff-presence', [\App\Http\Controllers\Api\ChatController::class, 'getStaffPresence']);
        Route::post('/chat/staff-ping', [\App\Http\Controllers\Api\ChatController::class, 'pingStaffPresence']);
        Route::post('/chat/staff-clear-presence', [\App\Http\Controllers\Api\ChatController::class, 'clearStaffPresence']);
    });

    // ==========================================
    // 👑 API DÀNH CHO ADMIN (Quyền = 0)
    // ==========================================
    Route::middleware(['role:0', 'log.activity'])->group(function () {
        // Quản lý Cửa hàng (Multi-store) (Chỉ Admin)
        Route::get('/admin/stores', [\App\Http\Controllers\Api\StoreController::class, 'index']);
        Route::post('/admin/stores', [\App\Http\Controllers\Api\StoreController::class, 'store'])->middleware(['feature.lock:STORE_MGMT']);
        Route::put('/admin/stores/{id}', [\App\Http\Controllers\Api\StoreController::class, 'update'])->middleware(['feature.lock:STORE_MGMT']);
        Route::put('/admin/stores/{id}/status', [\App\Http\Controllers\Api\StoreController::class, 'updateStatus'])->middleware(['feature.lock:STORE_MGMT']);

        // 📋 Nhật ký hệ thống
        Route::get('/admin/nhat-ky-he-thong', [NhatKyHeThongController::class, 'index']);

        // Khuyen Mai / Voucher Admin API
        Route::get('/admin/vouchers', [\App\Http\Controllers\Api\VoucherController::class, 'index']);
        Route::post('/admin/vouchers', [\App\Http\Controllers\Api\VoucherController::class, 'store'])->middleware(['feature.lock:PROMOTION']);
        Route::get('/admin/vouchers/{id}', [\App\Http\Controllers\Api\VoucherController::class, 'show']);
        Route::put('/admin/vouchers/{id}', [\App\Http\Controllers\Api\VoucherController::class, 'update'])->middleware(['feature.lock:PROMOTION']);
        Route::delete('/admin/vouchers/{id}', [\App\Http\Controllers\Api\VoucherController::class, 'destroy'])->middleware(['feature.lock:PROMOTION']);
    });

    // ==========================================
    // 👥 API DÀNH CHO ADMIN, QUẢN LÝ CHI NHÁNH & BAN GIÁM ĐỐC
    // ==========================================
    Route::middleware(['role:0,1,2,4,5,8', 'log.activity'])->group(function () {
        // Review CSKH / Admin API
        Route::get('/admin/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'getAdminReviews']);
        Route::put('/admin/reviews/{id}/toggle', [\App\Http\Controllers\Api\ReviewController::class, 'toggleReviewStatus']);
        Route::match(['put', 'post'], '/admin/reviews/{id}/toggle-visibility', [\App\Http\Controllers\Api\ReviewController::class, 'toggleReviewStatus']);
        Route::post('/admin/reviews/{id}/reply', [\App\Http\Controllers\Api\ReviewController::class, 'reply']);
        Route::get('/admin/transfers/requests', [\App\Http\Controllers\Api\TransferController::class, 'getRequests']);
        Route::get('/transfers/requests', [\App\Http\Controllers\Api\TransferController::class, 'getRequests']);
        Route::get('/admin/transfers/employees', [\App\Http\Controllers\Api\TransferController::class, 'getStoreEmployees']);
        Route::post('/admin/transfers/requests', [\App\Http\Controllers\Api\TransferController::class, 'createRequest'])->middleware(['feature.lock:INVENTORY_TRANSFER']);
        Route::post('/admin/transfers/requests/{id}/accept', [\App\Http\Controllers\Api\TransferController::class, 'acceptRequest'])->middleware(['feature.lock:INVENTORY_TRANSFER']);
        Route::post('/admin/transfers/requests/{id}/coordinate', [\App\Http\Controllers\Api\TransferController::class, 'coordinateRequest'])->middleware(['feature.lock:INVENTORY_TRANSFER']);
        Route::get('/admin/transfers', [\App\Http\Controllers\Api\TransferController::class, 'getTransfers']);
        Route::put('/admin/transfers/{id}/status', [\App\Http\Controllers\Api\TransferController::class, 'updateTransferStatus'])->middleware(['feature.lock:INVENTORY_TRANSFER']);
        Route::post('/admin/transfers/stock-info', [\App\Http\Controllers\Api\TransferController::class, 'getStockInfo']);
    });

    Route::middleware(['role:0,1,2,4,5,7,8', 'log.activity'])->group(function () {
        // Thống kê - Báo cáo
        Route::get('/admin/reports/revenue', [AdminController::class, 'revenueReport']);
        Route::get('/admin/reports/don-huy', [AdminController::class, 'thongKeDonHuy']);
        Route::get('/admin/reports/bi-dashboard', [AdminController::class, 'biDashboard']);
        Route::get('/admin/reports/top-products', [AdminController::class, 'topProducts']);
        Route::get('/admin/thong-ke/loi-nhuan', [AdminController::class, 'thongKeLoiNhuan']);
        Route::get('/admin/thong-ke/hao-hut', [AdminController::class, 'thongKeHaoHut']);
        Route::get('/admin/thong-ke/nhap-hang', [AdminController::class, 'thongKeNhapHang']);

        // Audit Logs (Nâng cao)
        Route::get('/admin/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);

        // Quản lý Nhân sự & Phân quyền Admin
        Route::get('/admin/users', [AdminController::class, 'listUsers']);
        Route::post('/admin/staff', [AdminController::class, 'createStaff'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::post('/admin/staff/import', [AdminController::class, 'importStaff'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::put('/admin/staff/{username}', [AdminController::class, 'updateStaff'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::delete('/admin/staff/{username}', [AdminController::class, 'deleteStaff'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::put('/admin/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::put('/admin/users/{id}/role', [AdminController::class, 'updateUserRole'])->middleware(['feature.lock:STAFF_MGMT']);
        Route::get('/admin/promote-customer/{makh}', [AdminController::class, 'promoteCustomer']);
        Route::get('/admin/cham-cong', [AdminController::class, 'getChamCong']);
        Route::post('/admin/cham-cong', [AdminController::class, 'chamCong']);
        Route::post('/admin/staff/scan-cccd', [\App\Http\Controllers\Api\CccdOcrController::class, 'scan']);

        // Quản lý Khóa Chức Năng Hệ Thống (Bảo trì)
        Route::get('/system/features', [\App\Http\Controllers\Api\SystemFeatureController::class, 'index']);
        Route::post('/admin/system/features/toggle', [\App\Http\Controllers\Api\SystemFeatureController::class, 'toggle']);

        // Quản lý Danh mục (Catalog)
        Route::post('/admin/categories', [CategoryController::class, 'store'])->middleware(['feature.lock:PRODUCT_CATALOG']);
        Route::put('/admin/categories/{id}', [CategoryController::class, 'update'])->middleware(['feature.lock:PRODUCT_CATALOG']);
        Route::delete('/admin/categories/{id}', [CategoryController::class, 'destroy'])->middleware(['feature.lock:PRODUCT_CATALOG']);

        // Quản lý Tin tức (Admin, Quản lý chi nhánh, CSKH & Giám đốc)
        Route::get('/admin/news', [\App\Http\Controllers\Api\NewsController::class, 'adminIndex']);
        Route::post('/admin/news', [\App\Http\Controllers\Api\NewsController::class, 'store']);
        Route::put('/admin/news/{id}', [\App\Http\Controllers\Api\NewsController::class, 'update']);
        Route::post('/admin/news/{id}', [\App\Http\Controllers\Api\NewsController::class, 'update']);
        Route::match(['put', 'post'], '/admin/news/{id}/toggle-visibility', [\App\Http\Controllers\Api\NewsController::class, 'toggleVisibility']);
        Route::delete('/admin/news/{id}', [\App\Http\Controllers\Api\NewsController::class, 'destroy']);
        Route::delete('/admin/news/comments/{id}', [\App\Http\Controllers\Api\NewsController::class, 'deleteComment']);

        // Quản lý Sản phẩm (Catalog)
        Route::post('/admin/products', [ProductController::class, 'store'])->middleware(['feature.lock:PRODUCT_CATALOG']);
        Route::match(['put', 'post'], '/admin/products/{id}', [ProductController::class, 'update'])->middleware(['feature.lock:PRODUCT_CATALOG']);
        Route::delete('/admin/products/{id}', [ProductController::class, 'destroy'])->middleware(['feature.lock:PRODUCT_CATALOG']);
        Route::put('/admin/products/{id}/restore', [ProductController::class, 'restore'])->middleware(['feature.lock:PRODUCT_CATALOG']);

    });

    // ==========================================
    // 💼 BÁN HÀNG, XỬ LÝ ĐƠN HÀNG, POS & KHÁCH HÀNG (Admin, Quản lý, Bán hàng, CSKH, Giám đốc)
    // ==========================================
    Route::middleware(['role:0,1,2,3,4,5,8', 'log.activity'])->group(function () {
        // POS Bán hàng
        Route::post('/admin/pos/checkout', [\App\Http\Controllers\Api\OrderController::class, 'storePOS'])->middleware(['throttle:orders', 'idempotency', 'feature.lock:POS']);

        // POS Shifts
        Route::get('/admin/pos/shifts', [\App\Http\Controllers\Api\PosShiftController::class, 'getShiftHistory']);
        Route::post('/admin/pos/shifts/open', [\App\Http\Controllers\Api\PosShiftController::class, 'openShift']);
        Route::post('/admin/pos/shifts/close', [\App\Http\Controllers\Api\PosShiftController::class, 'closeShift']);
        Route::get('/admin/pos/shifts/active', [\App\Http\Controllers\Api\PosShiftController::class, 'getActiveShift']);

        // Inventory Ledger
        Route::get('/admin/inventory/ledger', [\App\Http\Controllers\Api\InventoryLedgerController::class, 'getMovements']);

        // Quản lý Hóa đơn (Sửa, cập nhật trạng thái đơn online/tại quầy, chỉnh sửa danh sách bánh, upload ảnh minh chứng, hoàn tiền)
        Route::put('/hoa-don/{id}', [\App\Http\Controllers\Api\OrderController::class, 'updateStatus']);
        Route::put('/hoa-don/{id}/items', [\App\Http\Controllers\Api\OrderController::class, 'updateOrderItems']);
        Route::post('/hoa-don/{id}/refund', [\App\Http\Controllers\Api\OrderController::class, 'refundOrder']);
        Route::post('/orders/{id}/refund', [\App\Http\Controllers\Api\OrderController::class, 'refundOrder']);
        Route::post('/hoa-don/{id}/approve-return', [\App\Http\Controllers\Api\OrderController::class, 'adminApproveReturn']);
        Route::post('/orders/{id}/approve-return', [\App\Http\Controllers\Api\OrderController::class, 'adminApproveReturn']);
        Route::post('/hoa-don/{id}/reject-return', [\App\Http\Controllers\Api\OrderController::class, 'adminRejectReturn']);
        Route::post('/orders/{id}/reject-return', [\App\Http\Controllers\Api\OrderController::class, 'adminRejectReturn']);
        Route::post('/orders/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);
        Route::post('/hoa-don/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);
        Route::post('/my-orders/{id}/payment-receipt', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);

        // Quản lý Khách hàng (Thêm, Sửa, Xóa)
        Route::get('/customers/lookup', [\App\Http\Controllers\Api\CustomerController::class, 'lookupBySdt']);
        Route::post('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
        Route::put('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
        Route::put('/customers/{id}/restore', [\App\Http\Controllers\Api\CustomerController::class, 'restore']);

        // ============================================
        // 📋 Đặt Hàng Trước (Pre-Order)
        // ============================================
        Route::get('/admin/pre-orders', [DatHangTruocController::class, 'index']);
        Route::post('/admin/pre-orders', [DatHangTruocController::class, 'store']);
        Route::get('/admin/pre-orders/{id}', [DatHangTruocController::class, 'show']);
        Route::put('/admin/pre-orders/{id}', [DatHangTruocController::class, 'update']);
        Route::put('/admin/pre-orders/{id}/status', [DatHangTruocController::class, 'updateStatus']);
        Route::post('/admin/pre-orders/{id}/complete-payment', [DatHangTruocController::class, 'completePayment']);
    });

    // ==========================================
    // 🎧 API DÀNH CHO ADMIN, QUẢN LÝ VÀ CSKH (Quyền = 0, 1, 4)
    // ==========================================
    Route::middleware(['role:0,1,2,3,4,5,6,8', 'log.activity'])->group(function () {
        // Xem danh sách hóa đơn
        Route::get('/hoa-don', [\App\Http\Controllers\Api\OrderController::class, 'index']);
        Route::get('/hoa-don/{id}', [\App\Http\Controllers\Api\OrderController::class, 'show']);
        Route::get('/hoa-don/{id}/chi-tiet', [\App\Http\Controllers\Api\OrderController::class, 'getDetails']);

        // Xem danh sách khách hàng
        Route::get('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);

        // Tra cứu CSKH
        Route::get('/cskh/customer-info/{makh}', [\App\Http\Controllers\Api\CskhController::class, 'getCustomerInfo']);
        Route::get('/cskh/products', [\App\Http\Controllers\Api\CskhController::class, 'getProducts']);
        Route::get('/cskh/categories', [\App\Http\Controllers\Api\CskhController::class, 'getCategories']);
        Route::get('/cskh/product-stock', [\App\Http\Controllers\Api\CskhController::class, 'checkProductStock']);
        Route::get('/cskh/nearby-stores', [\App\Http\Controllers\Api\CskhController::class, 'findNearbyStores']);
        Route::get('/cskh/vouchers', [\App\Http\Controllers\Api\CskhController::class, 'getVouchers']);
        Route::post('/cskh/gift-voucher', [\App\Http\Controllers\Api\CskhController::class, 'giftVoucherToCustomer']);
        Route::post('/cskh/place-order/{makh}', [\App\Http\Controllers\Api\CskhController::class, 'placeOrderForCustomer'])->middleware(['feature.lock:ONLINE_ORDER']);
    });

    // ==========================================
    // 🛒 API DÀNH CHO KHÁCH HÀNG (Quyền = 3)
    // ==========================================
    Route::middleware(['role:0,1,2,3,6', 'log.activity'])->group(function () {
        // Mua hàng, xem giỏ hàng...
        Route::get('/cart', [\App\Http\Controllers\Api\CartController::class, 'index']);
        Route::post('/cart/add', [\App\Http\Controllers\Api\CartController::class, 'add'])->middleware(['feature.lock:ONLINE_ORDER']);
        Route::put('/cart/update', [\App\Http\Controllers\Api\CartController::class, 'updateQuantity'])->middleware(['feature.lock:ONLINE_ORDER']);
        Route::delete('/cart/remove/{masp}', [\App\Http\Controllers\Api\CartController::class, 'remove']);
        Route::post('/cart/checkout', [\App\Http\Controllers\Api\CartController::class, 'checkoutOnline'])->middleware(['throttle:orders', 'idempotency', 'feature.lock:ONLINE_ORDER']);
        Route::post('/cart/upload-receipt/{id}', [\App\Http\Controllers\Api\CartController::class, 'uploadPaymentReceipt']);
        Route::post('/vouchers/apply', [\App\Http\Controllers\Api\VoucherController::class, 'applyVoucher'])->middleware(['feature.lock:PROMOTION']);

        // Quản lý Sổ địa chỉ nhận hàng (Customer Address Book)
        Route::get('/addresses', [\App\Http\Controllers\Api\AddressController::class, 'index']);
        Route::get('/addresses/default', [\App\Http\Controllers\Api\AddressController::class, 'getDefault']);
        Route::post('/addresses', [\App\Http\Controllers\Api\AddressController::class, 'store']);
        Route::put('/addresses/{id}', [\App\Http\Controllers\Api\AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\AddressController::class, 'destroy']);
        Route::put('/addresses/{id}/default', [\App\Http\Controllers\Api\AddressController::class, 'setDefault']);

        // Voucher Wallet
        Route::post('/vouchers/collect', [\App\Http\Controllers\Api\VoucherController::class, 'collectVoucher'])->middleware(['feature.lock:PROMOTION']);
        Route::post('/vouchers/{id}/collect', [\App\Http\Controllers\Api\VoucherController::class, 'collectVoucher'])->middleware(['feature.lock:PROMOTION']);
        Route::get('/vouchers/my-wallet', [\App\Http\Controllers\Api\VoucherController::class, 'getMyWallet']);

        // Xem đơn hàng của tôi
        Route::get('/my-orders', [\App\Http\Controllers\Api\OrderController::class, 'myOrders']);
        Route::get('/my-orders/{id}/chi-tiet', [\App\Http\Controllers\Api\OrderController::class, 'getMyOrderDetails']);
        Route::post('/my-orders/{id}/cancel', [\App\Http\Controllers\Api\OrderController::class, 'customerCancelOrder']);
        Route::post('/my-orders/{id}/request-return', [\App\Http\Controllers\Api\OrderController::class, 'customerRequestReturn']);
        Route::post('/orders/{id}/request-return', [\App\Http\Controllers\Api\OrderController::class, 'customerRequestReturn']);
    });

    // ==========================================
    // 🏭 QUẢN LÝ LÔ HÀNG, NHẬP HÀNG, HSD & CHỮ KÝ SỐ
    // ==========================================
    Route::middleware(['log.activity'])->group(function () {

        // ── Nhà Cung Cấp ──────────────────────────────────────────────────
        Route::prefix('nha-cung-cap')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\NhaCungCapController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\NhaCungCapController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\NhaCungCapController::class, 'store']);
            Route::put('/{id}', [\App\Http\Controllers\Api\NhaCungCapController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\NhaCungCapController::class, 'destroy']);
        });

        // ── Lô Hàng & Cảnh Báo HSD ────────────────────────────────────────
        Route::prefix('lo-hang')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\LoHangController::class, 'index']);
            Route::get('/canh-bao-hsd', [\App\Http\Controllers\Api\LoHangController::class, 'getExpiredBatches']);
            Route::get('/canh-bao', [\App\Http\Controllers\Api\LoHangController::class, 'getExpiredBatches']);
            Route::post('/', [\App\Http\Controllers\Api\LoHangController::class, 'store']);
            Route::patch('/{id}/trang-thai', [\App\Http\Controllers\Api\LoHangController::class, 'updateTrangThai']);
            Route::put('/{id}/trang-thai', [\App\Http\Controllers\Api\LoHangController::class, 'updateTrangThai']);
            Route::post('/nhap', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'store']);
            Route::post('/xac-nhan-nhap-kho/{id}', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'xacNhanNhapKho']);
            Route::get('/{id}', [\App\Http\Controllers\Api\LoHangController::class, 'show']);
        });

        // ── Hóa Đơn Nhập Hàng (Supplier Import) ─────────────────────────
        Route::prefix('hoadon-nhap')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'store']);
            Route::post('/duyet-yeu-cau/{id}', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'duyetYeuCauNhapHang']);
            Route::get('/{id}', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'show']);
            Route::post('/{id}/xac-nhan-nhap-kho', [\App\Http\Controllers\Api\HoaDonNhapController::class, 'xacNhanNhapKho']);
        });

        // ── Biên Bản Hủy Sản Phẩm Hết Hạn ──────────────────────────────
        Route::prefix('bien-ban-huy')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\BienBanHuyController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\BienBanHuyController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\BienBanHuyController::class, 'show']);
        });

        // ── Chữ Ký Số Đa Bên ──────────────────────────────────────────────
        Route::prefix('chu-ky-so')->group(function () {
            Route::post('/sign-role', [\App\Http\Controllers\Api\SignatureController::class, 'signRole']);
            Route::post('/verify-document', [\App\Http\Controllers\Api\SignatureController::class, 'verifyDocumentSignatures']);
        });
    });

    // ==========================================
    // 📊 THỐNG KÊ NÂNG CAO (Quyền = 0, 1)
    // ==========================================
    Route::middleware(['role:0,1', 'log.activity'])->prefix('admin')->group(function () {

        // Lợi nhuận thực tế = Doanh thu - Chi phí nhập - Chi phí hủy
        // Params: thang, nam, macuahang (optional cho role 0)
        Route::get('/thong-ke/loi-nhuan', [AdminController::class, 'thongKeLoiNhuan']);

        // Phân tích nhập hàng: chi phí, top SP nhập, nhập theo NCC
        Route::get('/thong-ke/nhap-hang', [AdminController::class, 'thongKeNhapHang']);

        // Phân tích hao hụt: % lô hết hạn, lãng phí theo SP
        Route::get('/thong-ke/hao-hut', [AdminController::class, 'thongKeHaoHut']);

        // Cảnh báo HSD tổng hợp cho dashboard admin
        Route::get('/thong-ke/canh-bao-hsd', [AdminController::class, 'canhBaoHSD']);
    });
});

// ==========================================
// 🛡️ GIÁM SÁT KỸ THUẬT & PHÂN TÍCH (RESILIENT - VẪN CHẠY KHI MYSQL TẮT)
// ==========================================
Route::middleware(['resilient.admin'])->group(function () {
    // Giám sát Kỹ thuật & Bảo mật
    Route::get('/admin/system/performance', [\App\Http\Controllers\Api\SystemMonitorController::class, 'getPerformance']);
    Route::get('/admin/system/errors', [\App\Http\Controllers\Api\SystemMonitorController::class, 'getErrors']);
    Route::put('/admin/system/errors/{id}', [\App\Http\Controllers\Api\SystemMonitorController::class, 'updateErrorStatus']);
    Route::get('/admin/system/alerts', [\App\Http\Controllers\Api\SystemMonitorController::class, 'getAlertSettings']);
    Route::put('/admin/system/alerts', [\App\Http\Controllers\Api\SystemMonitorController::class, 'updateAlertSettings']);
    Route::post('/admin/system/alerts/test', [\App\Http\Controllers\Api\SystemMonitorController::class, 'testAlert']);

    // Báo cáo hoạt động & Số liệu người dùng
    Route::get('/admin/analytics/users', [\App\Http\Controllers\Api\SystemMonitorController::class, 'getUserAnalytics']);
    Route::get('/admin/analytics/behavior', [\App\Http\Controllers\Api\SystemMonitorController::class, 'getBehaviorAnalytics']);
});

Route::match(['get', 'post'], '/user/heartbeat', [\App\Http\Controllers\Api\SystemMonitorController::class, 'heartbeat']);
