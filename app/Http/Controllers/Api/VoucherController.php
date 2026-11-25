<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\KhuyenMai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class VoucherController extends Controller
{
    private static bool $schemaChecked = false;

    public static function ensureVoucherSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (!Schema::hasTable('voucher') && Schema::hasTable('khuyenmai')) {
                Schema::rename('khuyenmai', 'voucher');
            }

            if (!Schema::hasTable('voucher')) {
                Schema::create('voucher', function (Blueprint $table) {
                    $table->string('MA_VOUCHER', 50)->primary();
                    $table->string('TEN_VOUCHER', 255)->nullable();
                    $table->string('LOAI_GIAM', 50)->default('TIEN_MAT');
                    $table->decimal('GIA_TRI', 15, 2)->default(0);
                    $table->decimal('DON_TOI_THIEU', 15, 2)->default(0);
                    $table->decimal('GIAM_TOI_DA', 15, 2)->nullable();
                    $table->integer('SO_LUONG_GIOI_HAN')->default(100);
                    $table->integer('SO_LUONG_DA_DUNG')->default(0);
                    $table->dateTime('NGAY_BAT_DAU')->nullable();
                    $table->dateTime('NGAY_KET_THUC')->nullable();
                    $table->tinyInteger('TRANG_THAI')->default(1);
                });
            } else {
                // Kiểm tra chính xác tên cột phân biệt chữ hoa/thường trong INFORMATION_SCHEMA
                $dbName = DB::getDatabaseName();
                $cols = DB::select(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'voucher'",
                    [$dbName]
                );
                $exactCols = array_map(fn($c) => $c->COLUMN_NAME ?? $c->column_name ?? '', $cols);

                $renamePairs = [
                    'ma_voucher'         => 'MA_VOUCHER',
                    'maVoucher'          => 'MA_VOUCHER',
                    'ten_voucher'        => 'TEN_VOUCHER',
                    'tenVoucher'         => 'TEN_VOUCHER',
                    'loai_giam'          => 'LOAI_GIAM',
                    'loaiGiam'           => 'LOAI_GIAM',
                    'gia_tri_giam'       => 'GIA_TRI',
                    'gia_tri'            => 'GIA_TRI',
                    'giaTriGiam'         => 'GIA_TRI',
                    'don_hang_toi_thieu' => 'DON_TOI_THIEU',
                    'don_toi_thieu'      => 'DON_TOI_THIEU',
                    'donHangToiThieu'    => 'DON_TOI_THIEU',
                    'giam_toi_da'        => 'GIAM_TOI_DA',
                    'giamToiDa'          => 'GIAM_TOI_DA',
                    'so_luong'           => 'SO_LUONG_GIOI_HAN',
                    'so_luong_gioi_han'  => 'SO_LUONG_GIOI_HAN',
                    'soLuong'            => 'SO_LUONG_GIOI_HAN',
                    'da_su_dung'         => 'SO_LUONG_DA_DUNG',
                    'so_luong_da_dung'   => 'SO_LUONG_DA_DUNG',
                    'daSuDung'           => 'SO_LUONG_DA_DUNG',
                    'ngay_bat_dau'       => 'NGAY_BAT_DAU',
                    'ngayBatDau'         => 'NGAY_BAT_DAU',
                    'ngay_ket_thuc'      => 'NGAY_KET_THUC',
                    'ngayKetThuc'        => 'NGAY_KET_THUC',
                    'trang_thai'         => 'TRANG_THAI',
                    'trangThai'          => 'TRANG_THAI',
                ];

                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($renamePairs as $oldCol => $newCol) {
                    if (in_array($oldCol, $exactCols, true) && !in_array($newCol, $exactCols, true)) {
                        try {
                            DB::statement("ALTER TABLE `voucher` RENAME COLUMN `{$oldCol}` TO `{$newCol}`");
                            $exactCols[] = $newCol;
                        } catch (\Throwable $eRen) {}
                    }
                }

                if (!in_array('TEN_VOUCHER', $exactCols, true)) {
                    try {
                        Schema::table('voucher', function (Blueprint $t) {
                            $t->string('TEN_VOUCHER', 255)->nullable();
                        });
                    } catch (\Throwable $e) {}
                }
                if (!in_array('LOAI_GIAM', $exactCols, true)) {
                    try {
                        Schema::table('voucher', function (Blueprint $t) {
                            $t->string('LOAI_GIAM', 50)->default('TIEN_MAT');
                        });
                    } catch (\Throwable $e) {}
                }
                if (in_array('loai_giam_gia', $exactCols, true)) {
                    try {
                        DB::statement("ALTER TABLE `voucher` MODIFY COLUMN `loai_giam_gia` TINYINT DEFAULT 0 NULL");
                    } catch (\Throwable $e) {}
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            // Đảm bảo bảng voucher_khachhang tồn tại
            if (!Schema::hasTable('voucher_khachhang')) {
                Schema::create('voucher_khachhang', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('nguoidungId');
                    $table->string('maVoucher', 50);
                    $table->string('maDonHang', 50)->nullable();
                    $table->dateTime('ngayThuThap')->nullable();
                    $table->dateTime('ngaySuDung')->nullable();
                    $table->tinyInteger('trangThai')->default(0);
                    $table->timestamps();
                    $table->index(['nguoidungId', 'maVoucher']);
                });
            }

            // Đảm bảo có sẵn các voucher mẫu đang hoạt động
            $sampleVouchers = [
                [
                    'MA_VOUCHER' => 'GIAM20K',
                    'TEN_VOUCHER' => 'Giảm ngay 20.000đ cho đơn từ 100.000đ',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 20000,
                    'DON_TOI_THIEU' => 100000,
                    'GIAM_TOI_DA' => 20000,
                    'SO_LUONG_GIOI_HAN' => 200,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2025-01-01 00:00:00',
                    'NGAY_KET_THUC' => '2027-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'FREESHIP30K',
                    'TEN_VOUCHER' => 'Hỗ trợ phí giao bánh 30.000đ (Đơn từ 150K)',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 30000,
                    'DON_TOI_THIEU' => 150000,
                    'GIAM_TOI_DA' => 30000,
                    'SO_LUONG_GIOI_HAN' => 200,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2025-01-01 00:00:00',
                    'NGAY_KET_THUC' => '2027-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'BANHKEM15',
                    'TEN_VOUCHER' => 'Ưu đãi 15% tối đa 50.000đ cho đơn Bánh Kem',
                    'LOAI_GIAM' => 'PHAN_TRAM',
                    'GIA_TRI' => 15,
                    'DON_TOI_THIEU' => 200000,
                    'GIAM_TOI_DA' => 50000,
                    'SO_LUONG_GIOI_HAN' => 150,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2025-01-01 00:00:00',
                    'NGAY_KET_THUC' => '2027-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'THANHVIENVIP',
                    'TEN_VOUCHER' => 'Voucher Đặc Quyền Thành Viên - Giảm 50.000đ',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 50000,
                    'DON_TOI_THIEU' => 300000,
                    'GIAM_TOI_DA' => 50000,
                    'SO_LUONG_GIOI_HAN' => 100,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2025-01-01 00:00:00',
                    'NGAY_KET_THUC' => '2027-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
            ];

            foreach ($sampleVouchers as $sv) {
                $exists = DB::table('voucher')->where('MA_VOUCHER', $sv['MA_VOUCHER'])->first();
                if (!$exists) {
                    DB::table('voucher')->insert($sv);
                } else {
                    // Đảm bảo voucher mẫu luôn có tên và thời hạn hợp lệ (2025 - 2027)
                    $upd = [];
                    if (empty($exists->TEN_VOUCHER)) {
                        $upd['TEN_VOUCHER'] = $sv['TEN_VOUCHER'];
                    }
                    if (empty($exists->NGAY_BAT_DAU) || strtotime($exists->NGAY_BAT_DAU) > strtotime('2026-01-01')) {
                        $upd['NGAY_BAT_DAU'] = '2025-01-01 00:00:00';
                    }
                    if (empty($exists->NGAY_KET_THUC) || strtotime($exists->NGAY_KET_THUC) < strtotime('2027-06-01')) {
                        $upd['NGAY_KET_THUC'] = '2027-12-31 23:59:59';
                    }
                    if (!empty($upd)) {
                        DB::table('voucher')->where('MA_VOUCHER', $sv['MA_VOUCHER'])->update($upd);
                    }
                }
            }

            // Điền tên mặc định cho các voucher cũ bị thiếu TEN_VOUCHER
            DB::table('voucher')->whereNull('TEN_VOUCHER')->orWhere('TEN_VOUCHER', '')->update([
                'TEN_VOUCHER' => DB::raw("CONCAT('Ưu đãi mã ', MA_VOUCHER)")
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Voucher schema check warning: ' . $e->getMessage());
        }
    }

    private function resolveUserIds($user): array
    {
        if (!$user) {
            return [];
        }
        $ids = [(int) $user->id];
        if ($user->nguoidung && $user->nguoidung->id) {
            $ids[] = (int) $user->nguoidung->id;
        } else {
            $nd = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            if ($nd) {
                $ids[] = (int) $nd->id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function resolvePrimaryNguoiDungId($user): ?int
    {
        if (!$user) {
            return null;
        }
        if ($user->nguoidung && $user->nguoidung->id) {
            return (int) $user->nguoidung->id;
        }
        $nd = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
        return $nd ? (int) $nd->id : (int) $user->id;
    }

    public function index()
    {
        self::ensureVoucherSchema();
        $vouchers = Voucher::orderBy('NGAY_BAT_DAU', 'desc')->get();
        return response()->json($vouchers);
    }

    private function findVoucherByIdentifier($id)
    {
        self::ensureVoucherSchema();
        if (empty($id)) return null;

        $voucher = null;
        try {
            $voucher = Voucher::where('MA_VOUCHER', $id)->first();
        } catch (\Throwable $e) {}

        if (!$voucher) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('voucher', 'ma_voucher')) {
                    $voucher = Voucher::where('ma_voucher', $id)->first();
                }
            } catch (\Throwable $e) {}
        }

        if (!$voucher && is_numeric($id)) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('voucher', 'id')) {
                    $voucher = Voucher::where('id', $id)->first();
                }
            } catch (\Throwable $e) {}
        }

        return $voucher;
    }

    public function show($code)
    {
        $voucher = $this->findVoucherByIdentifier($code);
        if (!$voucher || !$voucher->isValid) {
            return response()->json(['error' => 'Mã Voucher không tồn tại hoặc đã hết hạn'], 404);
        }
        return response()->json($voucher);
    }

    public function store(Request $request)
    {
        self::ensureVoucherSchema();
        $code = strtoupper(trim($request->input('MA_VOUCHER') ?? $request->input('ma_voucher') ?? $request->input('maVoucher') ?? ''));
        $name = trim($request->input('TEN_VOUCHER') ?? $request->input('ten_voucher') ?? $request->input('tenVoucher') ?? $code);
        
        $loaiGiamRaw = $request->input('LOAI_GIAM') ?? $request->input('loai_giam') ?? $request->input('loai_giam_gia');
        $loaiGiam = ($loaiGiamRaw === 'TIEN_MAT' || $loaiGiamRaw == 1 || $loaiGiamRaw === '1') ? 'TIEN_MAT' : 'PHAN_TRAM';
        
        $giaTri = (float)($request->input('GIA_TRI') ?? $request->input('gia_tri') ?? $request->input('gia_tri_giam') ?? 0);
        $donToiThieu = (float)($request->input('DON_TOI_THIEU') ?? $request->input('don_toi_thieu') ?? $request->input('don_hang_toi_thieu') ?? 0);
        $giamToiDa = $request->input('GIAM_TOI_DA') ?? $request->input('giam_toi_da');
        $giamToiDaVal = ($giamToiDa !== null && $giamToiDa !== '') ? (float)$giamToiDa : null;
        
        $soLuong = (int)($request->input('SO_LUONG_GIOI_HAN') ?? $request->input('so_luong_gioi_han') ?? $request->input('soLuong') ?? 100);
        
        $ngayBatDau = $request->input('NGAY_BAT_DAU') ?? $request->input('ngay_bat_dau') ?? now()->toDateTimeString();
        $ngayKetThuc = $request->input('NGAY_KET_THUC') ?? $request->input('ngay_ket_thuc') ?? now()->addDays(7)->toDateTimeString();

        $request->merge([
            'MA_VOUCHER' => $code,
            'TEN_VOUCHER' => $name,
            'LOAI_GIAM' => $loaiGiam,
            'GIA_TRI' => $giaTri,
            'DON_TOI_THIEU' => $donToiThieu,
            'GIAM_TOI_DA' => $giamToiDaVal,
            'SO_LUONG_GIOI_HAN' => $soLuong,
            'NGAY_BAT_DAU' => $ngayBatDau,
            'NGAY_KET_THUC' => $ngayKetThuc,
        ]);

        $validated = $request->validate([
            'MA_VOUCHER' => 'required|string|max:50|unique:voucher,MA_VOUCHER',
            'TEN_VOUCHER' => 'required|string|max:255',
            'LOAI_GIAM' => 'required|string|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|numeric|min:0',
            'DON_TOI_THIEU' => 'required|numeric|min:0',
            'GIAM_TOI_DA' => 'nullable|numeric|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required',
            'NGAY_KET_THUC' => 'required',
        ], [
            'MA_VOUCHER.unique' => 'Mã Voucher "' . $code . '" đã tồn tại trong hệ thống! Vui lòng chọn mã khác.',
            'MA_VOUCHER.required' => 'Mã Voucher không được để trống.',
            'TEN_VOUCHER.required' => 'Tên chương trình Voucher không được để trống.',
        ]);

        $voucher = Voucher::create([
            'MA_VOUCHER' => $validated['MA_VOUCHER'],
            'TEN_VOUCHER' => $validated['TEN_VOUCHER'],
            'LOAI_GIAM' => $validated['LOAI_GIAM'],
            'GIA_TRI' => $validated['GIA_TRI'],
            'DON_TOI_THIEU' => $validated['DON_TOI_THIEU'],
            'GIAM_TOI_DA' => $validated['GIAM_TOI_DA'],
            'SO_LUONG_GIOI_HAN' => $validated['SO_LUONG_GIOI_HAN'],
            'SO_LUONG_DA_DUNG' => 0,
            'NGAY_BAT_DAU' => date('Y-m-d H:i:s', strtotime($validated['NGAY_BAT_DAU'])),
            'NGAY_KET_THUC' => date('Y-m-d H:i:s', strtotime($validated['NGAY_KET_THUC'])),
            'TRANG_THAI' => 1,
        ]);

        return response()->json(['message' => 'Tạo Voucher thành công', 'data' => $voucher], 201);
    }

    public function update(Request $request, $id)
    {
        $voucher = $this->findVoucherByIdentifier($id);
        if (!$voucher) {
            return response()->json(['error' => 'Không tìm thấy Voucher'], 404);
        }

        $name = trim($request->input('TEN_VOUCHER') ?? $request->input('ten_voucher') ?? $request->input('tenVoucher') ?? $voucher->TEN_VOUCHER);
        $loaiGiamRaw = $request->input('LOAI_GIAM') ?? $request->input('loai_giam') ?? $voucher->LOAI_GIAM;
        $loaiGiam = ($loaiGiamRaw === 'TIEN_MAT' || $loaiGiamRaw == 1 || $loaiGiamRaw === '1') ? 'TIEN_MAT' : 'PHAN_TRAM';
        
        $giaTri = (float)($request->input('GIA_TRI') ?? $request->input('gia_tri') ?? $voucher->GIA_TRI);
        $donToiThieu = (float)($request->input('DON_TOI_THIEU') ?? $request->input('don_toi_thieu') ?? $voucher->DON_TOI_THIEU);
        $giamToiDa = $request->input('GIAM_TOI_DA') ?? $request->input('giam_toi_da');
        $giamToiDaVal = ($giamToiDa !== null && $giamToiDa !== '') ? (float)$giamToiDa : null;
        $soLuong = (int)($request->input('SO_LUONG_GIOI_HAN') ?? $request->input('so_luong_gioi_han') ?? $voucher->SO_LUONG_GIOI_HAN);
        
        $ngayBatDau = $request->input('NGAY_BAT_DAU') ?? $voucher->NGAY_BAT_DAU;
        $ngayKetThuc = $request->input('NGAY_KET_THUC') ?? $voucher->NGAY_KET_THUC;
        $trangThai = $request->has('TRANG_THAI') ? ($request->input('TRANG_THAI') == '1' || $request->input('TRANG_THAI') === true || $request->input('TRANG_THAI') == 'true' ? 1 : 0) : $voucher->TRANG_THAI;

        $voucherCode = $voucher->MA_VOUCHER ?: $id;
        DB::table('voucher')->where('MA_VOUCHER', $voucherCode)->update([
            'TEN_VOUCHER' => $name,
            'LOAI_GIAM' => $loaiGiam,
            'GIA_TRI' => $giaTri,
            'DON_TOI_THIEU' => $donToiThieu,
            'GIAM_TOI_DA' => $giamToiDaVal,
            'SO_LUONG_GIOI_HAN' => $soLuong,
            'NGAY_BAT_DAU' => date('Y-m-d H:i:s', strtotime($ngayBatDau)),
            'NGAY_KET_THUC' => date('Y-m-d H:i:s', strtotime($ngayKetThuc)),
            'TRANG_THAI' => $trangThai,
        ]);

        $fresh = $this->findVoucherByIdentifier($voucherCode);
        return response()->json(['message' => 'Cập nhật Voucher thành công', 'data' => $fresh ?? $voucher]);
    }

    public function destroy($id)
    {
        $voucher = $this->findVoucherByIdentifier($id);
        if (!$voucher) {
            return response()->json(['error' => 'Không tìm thấy Voucher'], 404);
        }

        $voucherCode = $voucher->MA_VOUCHER ?? $voucher->ma_voucher ?? $id;

        // Xóa các liên kết trong ví của khách hàng nếu có
        try {
            DB::table('voucher_khachhang')->where('maVoucher', $voucherCode)->delete();
        } catch (\Throwable $e) {}

        try {
            DB::table('khuyenmai_khachhang')->where('maKhuyenMai', $voucherCode)->delete();
        } catch (\Throwable $e) {}

        DB::table('voucher')->where('MA_VOUCHER', $voucherCode)->delete();
        return response()->json(['message' => 'Xóa Voucher thành công']);
    }

    /**
     * Customer: Apply voucher to order
     */
    public function applyVoucher(Request $request)
    {
        self::ensureVoucherSchema();
        $code = strtoupper(trim($request->input('voucher_code') ?? $request->input('maVoucher') ?? $request->input('code') ?? ''));
        $subtotal = (float)($request->input('subtotal') ?? $request->input('tongTien') ?? 0);

        if (empty($code)) {
            return response()->json(['error' => 'Vui lòng nhập mã Voucher!'], 400);
        }

        $voucher = Voucher::where('MA_VOUCHER', $code)->first();
        if (!$voucher) {
            return response()->json(['error' => "Mã voucher '{$code}' không tồn tại trong hệ thống."], 404);
        }

        if (!$voucher->TRANG_THAI) {
            return response()->json(['error' => "Voucher '{$code}' hiện đang bị ngưng áp dụng."], 400);
        }

        if ($voucher->NGAY_BAT_DAU && strtotime($voucher->NGAY_BAT_DAU) > time()) {
            return response()->json(['error' => "Voucher '{$code}' chưa đến thời gian áp dụng."], 400);
        }
        if ($voucher->NGAY_KET_THUC && strtotime($voucher->NGAY_KET_THUC) < time()) {
            return response()->json(['error' => "Voucher '{$code}' đã hết hạn sử dụng."], 400);
        }

        if ($voucher->SO_LUONG_DA_DUNG >= $voucher->SO_LUONG_GIOI_HAN) {
            return response()->json(['error' => "Voucher '{$code}' đã hết số lượng lượt sử dụng."], 400);
        }

        if ($subtotal < (float)$voucher->DON_TOI_THIEU) {
            return response()->json([
                'error' => "Đơn hàng của bạn (" . number_format($subtotal) . "đ) chưa đạt giá trị tối thiểu " . number_format($voucher->DON_TOI_THIEU) . "đ để áp dụng voucher này."
            ], 400);
        }

        $user = auth('api')->user();
        if ($user) {
            $userIds = $this->resolveUserIds($user);

            $donHangTable = \Illuminate\Support\Facades\Schema::hasTable('donHang') ? 'donHang' : 'donhang';
            $usedInOrder = DB::table($donHangTable)
                ->whereIn('nguoidungId', $userIds)
                ->whereNotIn('trangThaiDonHang', [0, 6])
                ->where(function($q) use ($code) {
                    $q->where('maVoucher', $code)->orWhere('voucherId', $code);
                })
                ->exists();

            $usedInWallet = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('maVoucher', $code)
                ->where('trangThai', 1)
                ->exists();

            if ($usedInOrder || $usedInWallet) {
                return response()->json([
                    'error' => "Bạn đã sử dụng mã voucher '{$code}' cho một đơn hàng trước đó rồi! Mỗi tài khoản chỉ được dùng 1 lần."
                ], 400);
            }
        }

        $discountAmount = 0;
        if ($voucher->LOAI_GIAM === 'PHAN_TRAM') {
            $discountAmount = $subtotal * ((float)$voucher->GIA_TRI / 100);
            if ($voucher->GIAM_TOI_DA && (float)$voucher->GIAM_TOI_DA > 0) {
                $discountAmount = min($discountAmount, (float)$voucher->GIAM_TOI_DA);
            }
        } else {
            $discountAmount = min((float)$voucher->GIA_TRI, $subtotal);
        }

        $discountAmount = round($discountAmount);

        return response()->json([
            'success' => true,
            'voucher_code' => $voucher->MA_VOUCHER,
            'voucher_name' => $voucher->TEN_VOUCHER,
            'discount_amount' => $discountAmount,
            'loai_giam' => $voucher->LOAI_GIAM,
            'gia_tri' => $voucher->GIA_TRI,
            'don_toi_thieu' => $voucher->DON_TOI_THIEU,
            'giam_toi_da' => $voucher->GIAM_TOI_DA,
            'message' => "Đã áp dụng mã " . $voucher->MA_VOUCHER . " (Giảm " . number_format($discountAmount) . "đ)"
        ]);
    }

    /**
     * Customer: Save / Collect voucher into wallet
     */
    public function collectVoucher(Request $request, $id = null)
    {
        self::ensureVoucherSchema();
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Bạn cần đăng nhập để lưu voucher vào ví!'], 401);
        }

        $code = strtoupper(trim($id ?? $request->input('maVoucher') ?? $request->input('voucher_code') ?? $request->input('MA_VOUCHER') ?? ''));
        if (empty($code)) {
            return response()->json(['error' => 'Mã Voucher không hợp lệ!'], 400);
        }

        $voucher = Voucher::where('MA_VOUCHER', $code)->first();
        if (!$voucher || !$voucher->TRANG_THAI) {
            return response()->json(['error' => 'Mã Voucher không tồn tại hoặc đã ngưng hoạt động!'], 404);
        }

        if ($voucher->NGAY_KET_THUC && strtotime($voucher->NGAY_KET_THUC) < time()) {
            return response()->json(['error' => 'Voucher này đã hết hạn sử dụng!'], 400);
        }

        if ($voucher->SO_LUONG_DA_DUNG >= $voucher->SO_LUONG_GIOI_HAN) {
            return response()->json(['error' => 'Voucher này đã hết lượt thu thập!'], 400);
        }

        $userIds = $this->resolveUserIds($user);
        $primaryId = $this->resolvePrimaryNguoiDungId($user);

        $existing = DB::table('voucher_khachhang')
            ->whereIn('nguoidungId', $userIds)
            ->where('maVoucher', $code)
            ->first();

        if ($existing) {
            if ((int)$existing->trangThai === 1) {
                return response()->json(['error' => 'Bạn đã sử dụng Voucher này trước đó rồi!'], 400);
            }
            return response()->json([
                'success' => true,
                'already_saved' => true,
                'message' => 'Voucher này đã có sẵn trong Ví Voucher của bạn!'
            ], 200);
        }

        DB::table('voucher_khachhang')->insert([
            'nguoidungId' => $primaryId,
            'maVoucher' => $code,
            'ngayThuThap' => now(),
            'trangThai' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "🎉 Đã lưu thành công Voucher '{$code}' vào Ví Voucher của bạn!",
            'data' => $voucher
        ]);
    }

    /**
     * Customer: Get saved vouchers in wallet + available store vouchers to collect
     */
    public function getMyWallet(Request $request)
    {
        self::ensureVoucherSchema();
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'saved_vouchers' => [],
                'available_vouchers' => [],
                'used_vouchers' => [],
                'data' => [],
            ]);
        }

        $userIds = $this->resolveUserIds($user);

        $walletRows = DB::table('voucher_khachhang')
            ->whereIn('nguoidungId', $userIds)
            ->orderBy('id', 'desc')
            ->get();

        $savedCodes = $walletRows->where('trangThai', 0)->pluck('maVoucher')->map(fn($c) => strtoupper((string)$c))->toArray();
        $usedWalletCodes = $walletRows->where('trangThai', 1)->pluck('maVoucher')->map(fn($c) => strtoupper((string)$c))->toArray();

        $donHangTable = \Illuminate\Support\Facades\Schema::hasTable('donHang') ? 'donHang' : 'donhang';
        $usedOrderCodes = DB::table($donHangTable)
            ->whereIn('nguoidungId', $userIds)
            ->whereNotIn('trangThaiDonHang', [0, 6])
            ->whereNotNull('maVoucher')
            ->pluck('maVoucher')
            ->map(fn($c) => strtoupper((string)$c))
            ->toArray();

        $allUsedCodes = array_values(array_unique(array_merge($usedWalletCodes, $usedOrderCodes)));

        $activeVouchers = Voucher::where('TRANG_THAI', 1)
            ->where('NGAY_KET_THUC', '>=', now())
            ->orderBy('GIA_TRI', 'desc')
            ->get();

        $savedVouchers = [];
        $availableToCollect = [];

        foreach ($activeVouchers as $v) {
            $code = strtoupper($v->MA_VOUCHER);
            $isUsed = in_array($code, $allUsedCodes, true);
            $isSaved = in_array($code, $savedCodes, true);

            $item = $v->toArray();
            $item['is_saved'] = $isSaved;
            $item['is_used'] = $isUsed;
            $item['con_lai'] = max(0, (int)$v->SO_LUONG_GIOI_HAN - (int)$v->SO_LUONG_DA_DUNG);

            if ($isSaved && !$isUsed) {
                $savedVouchers[] = $item;
            }
            if (!$isUsed && $item['con_lai'] > 0) {
                $availableToCollect[] = $item;
            }
        }

        return response()->json([
            'saved_vouchers' => $savedVouchers,
            'available_vouchers' => $availableToCollect,
            'used_codes' => $allUsedCodes,
            'data' => $savedVouchers,
        ]);
    }

    /**
     * Customer: Suggest best voucher for current cart subtotal
     */
    public function suggestForOrder(Request $request)
    {
        self::ensureVoucherSchema();
        $subtotal = (float)($request->input('subtotal') ?? $request->input('tongTien') ?? 0);
        $user = auth('api')->user();
        $userUsedVouchers = [];
        $userSavedVouchers = [];

        if ($user) {
            $userIds = $this->resolveUserIds($user);

            $donHangTable = \Illuminate\Support\Facades\Schema::hasTable('donHang') ? 'donHang' : 'donhang';
            $usedInOrder = DB::table($donHangTable)
                ->whereIn('nguoidungId', $userIds)
                ->whereNotIn('trangThaiDonHang', [0, 6])
                ->whereNotNull('maVoucher')
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $usedInWallet = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('trangThai', 1)
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $userSavedVouchers = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('trangThai', 0)
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $userUsedVouchers = array_unique(array_merge($usedInOrder, $usedInWallet));
        }

        $allVouchers = Voucher::where('TRANG_THAI', 1)
            ->where('NGAY_BAT_DAU', '<=', now())
            ->where('NGAY_KET_THUC', '>=', now())
            ->where('SO_LUONG_DA_DUNG', '<', DB::raw('SO_LUONG_GIOI_HAN'))
            ->get();

        $eligibleVouchers = [];
        $bestVoucher = null;
        $maxDiscount = -1;

        foreach ($allVouchers as $v) {
            $code = strtoupper($v->MA_VOUCHER);
            if (in_array($code, $userUsedVouchers, true)) {
                continue;
            }

            $discount = 0;
            $isEligible = ($subtotal >= (float)$v->DON_TOI_THIEU);

            if ($v->LOAI_GIAM === 'PHAN_TRAM') {
                $discount = $subtotal * ((float)$v->GIA_TRI / 100);
                if ($v->GIAM_TOI_DA && (float)$v->GIAM_TOI_DA > 0) {
                    $discount = min($discount, (float)$v->GIAM_TOI_DA);
                }
            } else {
                $discount = min((float)$v->GIA_TRI, $subtotal);
            }
            $discount = round($discount);

            $vArr = $v->toArray();
            $vArr['calculated_discount'] = $discount;
            $vArr['is_eligible'] = $isEligible;
            $vArr['is_in_wallet'] = in_array($code, $userSavedVouchers, true);
            $vArr['min_order_diff'] = max(0, (float)$v->DON_TOI_THIEU - $subtotal);

            $eligibleVouchers[] = $vArr;

            if ($isEligible && $discount > $maxDiscount) {
                $maxDiscount = $discount;
                $bestVoucher = $vArr;
            }
        }

        usort($eligibleVouchers, function($a, $b) {
            if ($a['is_eligible'] !== $b['is_eligible']) {
                return $b['is_eligible'] <=> $a['is_eligible'];
            }
            if (($a['is_in_wallet'] ?? false) !== ($b['is_in_wallet'] ?? false)) {
                return ($b['is_in_wallet'] ?? false) <=> ($a['is_in_wallet'] ?? false);
            }
            return $b['calculated_discount'] <=> $a['calculated_discount'];
        });

        return response()->json([
            'best_voucher' => $bestVoucher,
            'available_vouchers' => $eligibleVouchers
        ]);
    }
}
