<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DatHangTruocController extends Controller
{
    private function getTables(): array
    {
        $donHang = Schema::hasTable('donHang') ? 'donHang' : 'donhang';
        $chiTiet = Schema::hasTable('chiTietDonHang') ? 'chiTietDonHang' : 'chitietdonhang';
        $sanPham = Schema::hasTable('sanPham') ? 'sanPham' : 'sanpham';

        try {
            if (!Schema::hasColumn($donHang, 'nhanVienId')) {
                Schema::table($donHang, function ($t) {
                    $t->unsignedBigInteger('nhanVienId')->nullable();
                });
            }
            if (!Schema::hasColumn($donHang, 'daXuatKho')) {
                Schema::table($donHang, function ($t) {
                    $t->tinyInteger('daXuatKho')->default(0);
                });
            }
            if (!Schema::hasColumn($donHang, 'minhChungThanhToan')) {
                Schema::table($donHang, function ($t) {
                    $t->longText('minhChungThanhToan')->nullable();
                });
            }
        } catch (\Throwable $e) {}

        return [$donHang, $chiTiet, $sanPham];
    }

    /**
     * Chuyển đổi mã sản phẩm (int, chuỗi "SP045", hoặc tên bánh) sang sanPham.id kiểu số nguyên
     */
    private function resolveSanPhamId(array $ct, string $sanPhamTable): int
    {
        $rawMasp = $ct['masp'] ?? $ct['maSP'] ?? $ct['sanPhamId'] ?? null;

        if ($rawMasp !== null && $rawMasp !== '') {
            if (is_numeric($rawMasp) && (int)$rawMasp > 0) {
                $exists = DB::table($sanPhamTable)->where('id', (int)$rawMasp)->value('id');
                if ($exists) {
                    return (int)$exists;
                }
            }

            if (is_string($rawMasp) && preg_match('/(\d+)/', $rawMasp, $m)) {
                $numId = (int)$m[1];
                if ($numId > 0) {
                    $exists = DB::table($sanPhamTable)->where('id', $numId)->value('id');
                    if ($exists) {
                        return (int)$exists;
                    }
                }
            }
        }

        $tenSp = trim((string)($ct['ten_sp'] ?? $ct['tenSP'] ?? ''));
        if ($tenSp !== '') {
            $byExactName = DB::table($sanPhamTable)->where('tenSanPham', $tenSp)->value('id');
            if ($byExactName) {
                return (int)$byExactName;
            }
            $byLikeName = DB::table($sanPhamTable)->where('tenSanPham', 'like', "%{$tenSp}%")->value('id');
            if ($byLikeName) {
                return (int)$byLikeName;
            }
        }

        $firstId = DB::table($sanPhamTable)->orderBy('id', 'asc')->value('id');
        return $firstId ? (int)$firstId : 1;
    }

    private function mapStatusToString($code)
    {
        $map = [
            0 => 'da_huy',
            1 => 'da_coc',
            2 => 'san_sang',
            7 => 'dang_giao',
            3 => 'dang_giao',
            4 => 'da_giao',
            5 => 'da_giao',
        ];
        return $map[(int)$code] ?? 'cho_xac_nhan';
    }

    private function mapStringToStatusCode($str)
    {
        $map = [
            'cho_xac_nhan' => 1,
            'da_coc' => 1,
            'san_sang' => 2,
            'dang_giao' => 3,
            'da_giao' => 4,
            'da_huy' => 0,
        ];
        return $map[$str] ?? 1;
    }

    /**
     * Lấy danh sách đơn đặt trước (loaiDonHang = 2)
     */
    public function index(Request $request)
    {
        $userLogin = Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        [$donHangTable, $chiTietTable, $sanPhamTable] = $this->getTables();

        $query = DB::table($donHangTable)
            ->where('loaiDonHang', 2);

        if ($macuahang) {
            $query->where('cuaHangId', $macuahang);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search, $donHangTable) {
                $q->where("{$donHangTable}.maDonHang", 'like', "%{$search}%")
                  ->orWhere("{$donHangTable}.ghiChu", 'like', "%{$search}%")
                  ->orWhere("{$donHangTable}.diaChiGiaoHang", 'like', "%{$search}%")
                  ->orWhereExists(function ($sub) use ($search, $donHangTable) {
                      $sub->select(DB::raw(1))
                          ->from('nguoidung')
                          ->whereColumn('nguoidung.id', "{$donHangTable}.nguoidungId")
                          ->where(function ($subQ) use ($search) {
                              $subQ->where('nguoidung.hoTen', 'like', "%{$search}%")
                                   ->orWhere('nguoidung.soDienThoai', 'like', "%{$search}%");
                          });
                  });
            });
        }

        if ($request->filled('trangThai')) {
            $ttFilter = $request->trangThai;
            if ($ttFilter === 'dang_giao') {
                $query->whereIn('trangThaiDonHang', [3, 7]);
            } elseif ($ttFilter === 'da_giao') {
                $query->whereIn('trangThaiDonHang', [4, 5]);
            } elseif ($ttFilter === 'da_huy') {
                $query->where('trangThaiDonHang', 0);
            } else {
                $code = $this->mapStringToStatusCode($ttFilter);
                $query->where('trangThaiDonHang', $code);
            }
        }

        $orders = $query->orderBy('createdAt', 'desc')->get();

        $result = $orders->map(function ($o) use ($chiTietTable, $sanPhamTable) {
            $cust = DB::table('nguoidung')->where('id', $o->nguoidungId)->first();
            $staffIdVal = $o->nhanVienId ?? null;
            $staff = $staffIdVal ? DB::table('nguoidung')->where('id', $staffIdVal)->first() : null;
            $staffName = $staff ? $staff->hoTen : 'Quản trị viên';
            $details = DB::table($chiTietTable)
                ->leftJoin($sanPhamTable, "{$chiTietTable}.sanPhamId", '=', "{$sanPhamTable}.id")
                ->where("{$chiTietTable}.donHangId", $o->id)
                ->select("{$chiTietTable}.*", "{$sanPhamTable}.tenSanPham")
                ->get();

            $ngayHenLay = null;
            if (preg_match('/\[Hẹn lấy:\s*([^\]]+)\]/', $o->ghiChu, $m)) {
                $ngayHenLay = $m[1];
            }

            $tienCoc = (float)$o->soTienGiam;
            $tongTien = (float)$o->tongThanhToan;
            $tienConLai = max(0, $tongTien - $tienCoc);

            // Timezone GMT+7 Vietnam
            $ngayDatIct = $o->createdAt ? Carbon::parse($o->createdAt)->format('Y-m-d H:i:s') : null;

            return [
                'id' => $o->maDonHang,
                'maDHT' => $o->maDonHang,
                'dbId' => $o->id,
                'macuahang' => $o->cuaHangId,
                'maKH' => $o->nguoidungId,
                'hoTenKH' => $cust ? $cust->hoTen : 'Khách hàng',
                'sdtKH' => $cust ? $cust->soDienThoai : '',
                'nhanVienLap' => $staffName,
                'tenNhanVien' => $staffName,
                'ngayDat' => $ngayDatIct,
                'ngayHenLay' => $ngayHenLay ?: $ngayDatIct,
                'ghiChu' => trim(preg_replace('/\[Hẹn lấy:\s*[^\]]+\]\s*/', '', $o->ghiChu ?? '')),
                'tongTien' => $tongTien,
                'tienCoc' => $tienCoc,
                'phuongThucCoc' => $o->phuongThucThanhToan == 2 ? 'Chuyển khoản' : 'Tiền mặt',
                'hinhThucNhan' => ($o->diaChiGiaoHang && $o->diaChiGiaoHang !== 'Nhận tại cửa hàng') ? 'giao_tan_noi' : 'tai_cua_hang',
                'diaChiGiao' => $o->diaChiGiaoHang,
                'phiGiaoHang' => (float)$o->phiVanChuyen,
                'maVanDon' => $o->maVanDonGhn ?? '',
                'maVanDonGhn' => $o->maVanDonGhn ?? '',
                'trangThaiDonHang' => (int)$o->trangThaiDonHang,
                'trangThaiThanhToan' => (int)$o->trangThaiThanhToan,
                'trangThai' => $this->mapStatusToString($o->trangThaiDonHang),
                'chuKy' => $o->minhChungThanhToan,
                'tienConLai' => $tienConLai,
                'chiTiets' => $details->map(function ($ct) {
                    return [
                        'id' => $ct->id,
                        'maSP' => $ct->sanPhamId,
                        'tenSP' => $ct->tenSanPham ?? 'Bánh đặt theo yêu cầu',
                        'soLuong' => (int)$ct->soLuong,
                        'donGia' => (float)$ct->donGia,
                        'thanhTien' => (float)$ct->thanhTien,
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
            'total' => $result->count()
        ]);
    }

    /**
     * Tạo mới đơn đặt bánh trước
     */
    public function store(Request $request)
    {
        $request->validate([
            'hoten_kh' => 'required|string|max:255',
            'sdt_kh' => 'required|string|max:20',
            'ngay_hen_lay' => 'required',
            'chi_tiet' => 'required|array|min:1',
        ]);

        [$donHangTable, $chiTietTable, $sanPhamTable] = $this->getTables();

        $userLogin = Auth::guard('api')->user();
        $storeId = 1;

        if ($userLogin && $userLogin->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $storeId = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        $sdt = trim($request->sdt_kh);
        $cust = DB::table('nguoidung')->where('soDienThoai', $sdt)->first();
        $nowStr = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        if (!$cust) {
            try {
                $userId = DB::table('nguoidung')->insertGetId([
                    'taiKhoanId' => null,
                    'hoTen' => trim($request->hoten_kh),
                    'soDienThoai' => $sdt,
                    'gioiTinh' => 1,
                    'loaiNguoiDung' => 0,
                    'createdAt' => $nowStr,
                    'updatedAt' => $nowStr,
                ]);
            } catch (\Throwable $ex) {
                $userId = 1;
            }
        } else {
            $userId = $cust->id;
            DB::table('nguoidung')->where('id', $userId)->update([
                'hoTen' => trim($request->hoten_kh),
                'updatedAt' => $nowStr,
            ]);
        }

        DB::beginTransaction();
        try {
            $now = Carbon::now('Asia/Ho_Chi_Minh');
            $maDHT = 'DHT' . $now->format('YmdHis') . rand(10, 99);

            $chiTietData = $request->input('chi_tiet', []);
            $tongTienBanh = 0;
            foreach ($chiTietData as $ct) {
                $sl = (int)($ct['so_luong'] ?? 1);
                $dg = (float)($ct['don_gia'] ?? 0);
                $tongTienBanh += ($sl * $dg);
            }

            $phiGiaoHang = (float)($request->input('phi_giao_hang', 0));
            $tongTien = $tongTienBanh + $phiGiaoHang;
            $tienCoc = (float)($request->input('tien_coc', 0));

            if ($tienCoc < 0) {
                return response()->json(['error' => 'Tiền cọc không được nhỏ hơn 0.'], 422);
            }
            if ($tienCoc > $tongTien) {
                return response()->json([
                    'error' => 'Tiền cọc (' . number_format($tienCoc, 0, ',', '.') . ' đ) không được vượt quá tổng giá trị đơn hàng (' . number_format($tongTien, 0, ',', '.') . ' đ).'
                ], 422);
            }

            $ngayHen = $request->input('ngay_hen_lay');
            if ($ngayHen) {
                // Hỗ trợ cả định dạng Y-m-d H:i:s lẫn ISO UTC (kèm chữ Z)
                $henCarbon = str_ends_with($ngayHen, 'Z')
                    ? Carbon::parse($ngayHen)->setTimezone('Asia/Ho_Chi_Minh')
                    : Carbon::parse($ngayHen, 'Asia/Ho_Chi_Minh');

                if ($henCarbon->lt($now->copy()->subMinutes(5))) {
                    return response()->json([
                        'error' => 'Thời gian hẹn lấy bánh (' . $henCarbon->format('H:i d/m/Y') . ') không được ở trong quá khứ. Vui lòng chọn thời gian sau thời điểm hiện tại (' . $now->format('H:i') . ').'
                    ], 422);
                }
                $ngayHenStr = $henCarbon->format('Y-m-d H:i:s');
            } else {
                $ngayHenStr = $now->copy()->addHours(2)->format('Y-m-d H:i:s');
            }

            $statusCode = 0; // cho_xac_nhan
            if ($tienCoc > 0) {
                $statusCode = 1; // da_coc
            }

            $fullGhiChu = "[Hẹn lấy: " . $ngayHenStr . "] " . ($request->input('ghi_chu') ?? '');
            $phuongThucCoc = $request->input('phuong_thuc_coc') === 'Chuyển khoản' ? 2 : 1;

            $staffId = null;
            $savedSig = null;
            if ($userLogin) {
                $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                $staffId = $staffProfile ? $staffProfile->id : $userLogin->id;
                $savedSig = $staffProfile?->chuKySo ?? $userLogin->chuKySo ?? null;
            }

            $insertPayload = [
                'maDonHang' => $maDHT,
                'nguoidungId' => $userId,
                'cuaHangId' => $storeId,
                'loaiDonHang' => 2,
                'tongTienHang' => $tongTienBanh,
                'soTienGiam' => $tienCoc,
                'phiVanChuyen' => $phiGiaoHang,
                'tongThanhToan' => $tongTien,
                'phuongThucThanhToan' => $phuongThucCoc,
                'trangThaiThanhToan' => $tienCoc > 0 ? 1 : 0,
                'trangThaiDonHang' => $statusCode,
                'diaChiGiaoHang' => $request->input('hinh_thuc_nhan') === 'giao_tan_noi' ? ($request->input('dia_chi_giao') ?: 'Giao tận nơi') : 'Nhận tại cửa hàng',
                'ghiChu' => trim($fullGhiChu),
                'createdAt' => $nowStr,
                'updatedAt' => $nowStr,
            ];

            if (Schema::hasColumn($donHangTable, 'nhanVienId')) {
                $insertPayload['nhanVienId'] = $staffId;
            }
            if (Schema::hasColumn($donHangTable, 'minhChungThanhToan')) {
                $insertPayload['minhChungThanhToan'] = $request->input('chu_ky') ?: $savedSig;
            }
            if ($request->input('hinh_thuc_nhan') === 'giao_tan_noi') {
                if ($request->filled('to_ward_code') && Schema::hasColumn($donHangTable, 'toWardCode')) {
                    $insertPayload['toWardCode'] = (string)$request->input('to_ward_code');
                }
                if ($request->filled('to_district_id') && Schema::hasColumn($donHangTable, 'toDistrictId')) {
                    $insertPayload['toDistrictId'] = (int)$request->input('to_district_id');
                }
            }

            $orderId = DB::table($donHangTable)->insertGetId($insertPayload);

            foreach ($chiTietData as $ct) {
                $spId = $this->resolveSanPhamId((array)$ct, $sanPhamTable);
                $sl = (int)($ct['so_luong'] ?? 1);
                $dg = (float)($ct['don_gia'] ?? 0);

                DB::table($chiTietTable)->insert([
                    'donHangId' => $orderId,
                    'sanPhamId' => $spId,
                    'soLuong' => $sl,
                    'donGia' => $dg,
                    'thanhTien' => $sl * $dg,
                    'createdAt' => $nowStr,
                    'updatedAt' => $nowStr,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo đơn đặt bánh trước thành công!',
                'data' => [
                    'maDHT' => $maDHT,
                    'id' => $maDHT
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo đơn đặt hàng trước: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật / Sửa thông tin đơn đặt trước
     */
    public function update(Request $request, $id)
    {
        [$donHangTable, $chiTietTable, $sanPhamTable] = $this->getTables();
        $o = DB::table($donHangTable)->where('maDonHang', $id)->first();
        if (!$o) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không tồn tại'], 404);
        }

        $request->validate([
            'hoten_kh' => 'required|string|max:255',
            'sdt_kh' => 'required|string|max:20',
            'ngay_hen_lay' => 'required',
            'chi_tiet' => 'required|array|min:1',
        ]);

        $sdt = trim($request->sdt_kh);
        $cust = DB::table('nguoidung')->where('soDienThoai', $sdt)->first();
        $nowStr = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        if (!$cust) {
            try {
                $userId = DB::table('nguoidung')->insertGetId([
                    'taiKhoanId' => null,
                    'hoTen' => trim($request->hoten_kh),
                    'soDienThoai' => $sdt,
                    'gioiTinh' => 1,
                    'loaiNguoiDung' => 0,
                    'createdAt' => $nowStr,
                    'updatedAt' => $nowStr,
                ]);
            } catch (\Throwable $ex) {
                $userId = $o->nguoidungId ?: 1;
            }
        } else {
            $userId = $cust->id;
            DB::table('nguoidung')->where('id', $userId)->update([
                'hoTen' => trim($request->hoten_kh),
                'updatedAt' => $nowStr,
            ]);
        }

        DB::beginTransaction();
        try {
            $chiTietData = $request->input('chi_tiet', []);
            $tongTienBanh = 0;
            foreach ($chiTietData as $ct) {
                $sl = (int)($ct['so_luong'] ?? 1);
                $dg = (float)($ct['don_gia'] ?? 0);
                $tongTienBanh += ($sl * $dg);
            }

            $phiGiaoHang = (float)($request->input('phi_giao_hang', 0));
            $tongTien = $tongTienBanh + $phiGiaoHang;
            $tienCoc = (float)($request->input('tien_coc', 0));

            $ngayHen = $request->input('ngay_hen_lay');
            $fullGhiChu = "[Hẹn lấy: " . $ngayHen . "] " . ($request->input('ghi_chu') ?? '');
            $phuongThucCoc = $request->input('phuong_thuc_coc') === 'Chuyển khoản' ? 2 : 1;

            $updateData = [
                'nguoidungId' => $userId,
                'tongTienHang' => $tongTienBanh,
                'soTienGiam' => $tienCoc,
                'phiVanChuyen' => $phiGiaoHang,
                'tongThanhToan' => $tongTien,
                'phuongThucThanhToan' => $phuongThucCoc,
                'trangThaiThanhToan' => $tienCoc > 0 ? 1 : 0,
                'diaChiGiaoHang' => $request->input('hinh_thuc_nhan') === 'giao_tan_noi' ? ($request->input('dia_chi_giao') ?: 'Giao tận nơi') : 'Nhận tại cửa hàng',
                'ghiChu' => trim($fullGhiChu),
                'updatedAt' => $nowStr,
            ];

            if ($request->filled('chu_ky') && Schema::hasColumn($donHangTable, 'minhChungThanhToan')) {
                $updateData['minhChungThanhToan'] = $request->input('chu_ky');
            }

            DB::table($donHangTable)->where('id', $o->id)->update($updateData);

            // Re-insert line items
            DB::table($chiTietTable)->where('donHangId', $o->id)->delete();
            foreach ($chiTietData as $ct) {
                $spId = $this->resolveSanPhamId((array)$ct, $sanPhamTable);
                $sl = (int)($ct['so_luong'] ?? 1);
                $dg = (float)($ct['don_gia'] ?? 0);

                DB::table($chiTietTable)->insert([
                    'donHangId' => $o->id,
                    'sanPhamId' => $spId,
                    'soLuong' => $sl,
                    'donGia' => $dg,
                    'thanhTien' => $sl * $dg,
                    'createdAt' => $nowStr,
                    'updatedAt' => $nowStr,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đơn đặt bánh trước thành công!'
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật đơn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chi tiết 1 đơn đặt trước
     */
    public function show($id)
    {
        [$donHangTable, $chiTietTable, $sanPhamTable] = $this->getTables();
        $o = DB::table($donHangTable)->where('maDonHang', $id)->first();

        if (!$o) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt hàng trước'
            ], 404);
        }

        $cust = DB::table('nguoidung')->where('id', $o->nguoidungId)->first();
        $storeObj = DB::table('cuahang')->where('id', $o->cuaHangId)->first();
        $staffIdVal = $o->nhanVienId ?? null;
        $staff = $staffIdVal ? DB::table('nguoidung')->where('id', $staffIdVal)->first() : null;
        $staffName = $staff ? $staff->hoTen : 'Quản trị viên';
        $details = DB::table($chiTietTable)
            ->leftJoin($sanPhamTable, "{$chiTietTable}.sanPhamId", '=', "{$sanPhamTable}.id")
            ->where("{$chiTietTable}.donHangId", $o->id)
            ->select("{$chiTietTable}.*", "{$sanPhamTable}.tenSanPham")
            ->get();

        $ngayHenLay = null;
        if (preg_match('/\[Hẹn lấy:\s*([^\]]+)\]/', $o->ghiChu, $m)) {
            $ngayHenLay = $m[1];
        }

        $tienCoc = (float)$o->soTienGiam;
        $tongTien = (float)$o->tongThanhToan;
        $tienConLai = max(0, $tongTien - $tienCoc);

        $ngayDatIct = $o->createdAt ? Carbon::parse($o->createdAt)->format('Y-m-d H:i:s') : null;

        $isGiaoTanNoi = ($o->diaChiGiaoHang && $o->diaChiGiaoHang !== 'Nhận tại cửa hàng');
        if ($isGiaoTanNoi && empty($o->maVanDonGhn)) {
            $orderModel = \App\Models\DonHang::find($o->id);
            if ($orderModel) {
                try {
                    $ghnRes = \App\Http\Controllers\Api\GhnController::createShippingOrderForDonHang($orderModel);
                    if (!empty($ghnRes['order_code'])) {
                        $o->maVanDonGhn = $ghnRes['order_code'];
                    }
                } catch (\Throwable $eGhn) {}
            }
        }

        $data = [
            'id' => $o->maDonHang,
            'maDHT' => $o->maDonHang,
            'dbId' => $o->id,
            'macuahang' => $o->cuaHangId,
            'cuaHang' => $storeObj ? ['tenCuaHang' => $storeObj->tenCuaHang] : null,
            'maKH' => $o->nguoidungId,
            'hoTenKH' => $cust ? $cust->hoTen : 'Khách hàng',
            'sdtKH' => $cust ? $cust->soDienThoai : '',
            'nhanVienLap' => $staffName,
            'tenNhanVien' => $staffName,
            'ngayDat' => $ngayDatIct,
            'ngayHenLay' => $ngayHenLay ?: $ngayDatIct,
            'ghiChu' => trim(preg_replace('/\[Hẹn lấy:\s*[^\]]+\]\s*/', '', $o->ghiChu ?? '')),
            'tongTien' => $tongTien,
            'tienCoc' => $tienCoc,
            'phuongThucCoc' => $o->phuongThucThanhToan == 2 ? 'Chuyển khoản' : 'Tiền mặt',
            'hinhThucNhan' => ($o->diaChiGiaoHang && $o->diaChiGiaoHang !== 'Nhận tại cửa hàng') ? 'giao_tan_noi' : 'tai_cua_hang',
            'diaChiGiao' => $o->diaChiGiaoHang,
            'phiGiaoHang' => (float)$o->phiVanChuyen,
            'maVanDon' => $o->maVanDonGhn ?? '',
            'maVanDonGhn' => $o->maVanDonGhn ?? '',
            'trangThaiDonHang' => (int)$o->trangThaiDonHang,
            'trangThaiThanhToan' => (int)$o->trangThaiThanhToan,
            'trangThai' => $this->mapStatusToString($o->trangThaiDonHang),
            'chuKy' => $o->minhChungThanhToan,
            'tienConLai' => $tienConLai,
            'chiTiets' => $details->map(function ($ct) {
                return [
                    'id' => $ct->id,
                    'maSP' => $ct->sanPhamId,
                    'tenSP' => $ct->tenSanPham ?? 'Bánh đặt theo yêu cầu',
                    'soLuong' => (int)$ct->soLuong,
                    'donGia' => (float)$ct->donGia,
                    'thanhTien' => (float)$ct->thanhTien,
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Cập nhật trạng thái
     */
    /**
     * Trừ tồn kho khi đơn chuyển sang Sẵn sàng (2) hoặc Đã giao (3, 4)
     */
    private function deductStockForOrder($order)
    {
        [, $chiTietTable] = $this->getTables();
        $details = DB::table($chiTietTable)->where('donHangId', $order->id)->get();
        $storeId = $order->cuaHangId ?? 1;

        foreach ($details as $ct) {
            $qty = (int) $ct->soLuong;
            $spId = $ct->sanPhamId;
            if ($qty <= 0) continue;

            // 1. Giảm tồn kho chi nhánh
            $tonKho = \App\Models\TonKhoChiNhanh::firstOrCreate(
                ['cuaHangId' => $storeId, 'sanPhamId' => $spId],
                ['soLuongTon' => 0]
            );
            $tonKho->decrement('soLuongTon', $qty);
            $tonSau = (int) $tonKho->fresh()->soLuongTon;

            // 2. Ghi sổ nhật ký kho (loaiBienDong 0 = Xuất bán đơn hàng)
            \App\Models\NhatKyKho::create([
                'cuaHangId' => $storeId,
                'sanPhamId' => $spId,
                'loaiBienDong' => 0,
                'soLuongThayDoi' => -$qty,
                'soLuongTonSau' => $tonSau,
                'maThamChieu' => $order->maDonHang,
            ]);
        }
    }

    /**
     * Hoàn trả tồn kho khi đơn bị hủy (0)
     */
    private function restoreStockForOrder($order)
    {
        [, $chiTietTable] = $this->getTables();
        $details = DB::table($chiTietTable)->where('donHangId', $order->id)->get();
        $storeId = $order->cuaHangId ?? 1;

        foreach ($details as $ct) {
            $qty = (int) $ct->soLuong;
            $spId = $ct->sanPhamId;
            if ($qty <= 0) continue;

            $tonKho = \App\Models\TonKhoChiNhanh::firstOrCreate(
                ['cuaHangId' => $storeId, 'sanPhamId' => $spId],
                ['soLuongTon' => 0]
            );
            $tonKho->increment('soLuongTon', $qty);
            $tonSau = (int) $tonKho->fresh()->soLuongTon;

            // 2. Ghi sổ nhật ký kho (loaiBienDong 1 = Nhập hoàn trả kho)
            \App\Models\NhatKyKho::create([
                'cuaHangId' => $storeId,
                'sanPhamId' => $spId,
                'loaiBienDong' => 1,
                'soLuongThayDoi' => $qty,
                'soLuongTonSau' => $tonSau,
                'maThamChieu' => $order->maDonHang,
            ]);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        [$donHangTable] = $this->getTables();
        $o = DB::table($donHangTable)->where('maDonHang', $id)->first();
        if (!$o) {
            return response()->json(['success' => false, 'message' => 'Đơn không tồn tại'], 404);
        }

        $trangThaiStr = $request->input('trang_thai') ?? $request->input('trangThai');
        $ghnMsg = '';
        if ($trangThaiStr) {
            $code = $this->mapStringToStatusCode($trangThaiStr);
            $daXuatKho = (int) ($o->daXuatKho ?? 0);

            // Đảm bảo schema chạy trước bên ngoài transaction để không gây implicit commit
            try {
                \App\Services\InventoryLedgerService::ensureSchema();
            } catch (\Throwable $eSchema) {}

            DB::beginTransaction();
            try {
                // Khi chuyển sang Sẵn sàng (2), Đang giao (3), Đã giao (4) và chưa trừ kho -> Trừ kho
                if (($code == 2 || $code == 3 || $code == 4) && $daXuatKho == 0) {
                    $this->deductStockForOrder($o);
                    $daXuatKho = 1;
                }
                // Khi hủy đơn (0 hoặc 'da_huy') mà trước đó đã trừ kho -> Hoàn trả kho
                else if (($code == 0 || $trangThaiStr === 'da_huy') && $daXuatKho == 1) {
                    $this->restoreStockForOrder($o);
                    $daXuatKho = 0;
                }

                $upd = [
                    'trangThaiDonHang' => $code,
                    'updatedAt' => now()
                ];
                if (Schema::hasColumn($donHangTable, 'daXuatKho')) {
                    $upd['daXuatKho'] = $daXuatKho;
                }
                DB::table($donHangTable)->where('id', $o->id)->update($upd);

                if (DB::transactionLevel() > 0) {
                    DB::commit();
                }
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                \Illuminate\Support\Facades\Log::error("Error updating pre-order status: " . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi cập nhật trạng thái kho: ' . $e->getMessage()
                ], 500);
            }

            // Tự động tạo đơn GHN khi chuyển sang Sẵn sàng (2) hoặc Đang giao (3) nếu là đơn Giao tận nơi
            $isGiaoTanNoi = ($o->diaChiGiaoHang && $o->diaChiGiaoHang !== 'Nhận tại cửa hàng');
            if ($isGiaoTanNoi && ($code == 2 || $code == 3) && empty($o->maVanDonGhn)) {
                $orderModel = \App\Models\DonHang::find($o->id);
                if ($orderModel) {
                    $ghnRes = \App\Http\Controllers\Api\GhnController::createShippingOrderForDonHang($orderModel);
                    if (!empty($ghnRes['order_code'])) {
                        $ghnMsg = " Đã tự động tạo vận đơn GHN: {$ghnRes['order_code']}.";
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Cập nhật trạng thái thành công!{$ghnMsg}"
        ]);
    }

    /**
     * Hoàn tất thanh toán tiền còn lại
     */
    public function completePayment(Request $request, $id)
    {
        [$donHangTable] = $this->getTables();
        $o = DB::table($donHangTable)->where('maDonHang', $id)->first();
        if (!$o) {
            return response()->json(['success' => false, 'message' => 'Đơn không tồn tại'], 404);
        }

        try {
            \App\Services\InventoryLedgerService::ensureSchema();
        } catch (\Throwable $eSchema) {}

        $isGiaoTanNoi = ($o->diaChiGiaoHang && $o->diaChiGiaoHang !== 'Nhận tại cửa hàng');

        // PHÂN BIỆT RÕ HÌNH THỨC NHẬN HÀNG:
        // 1. Nhận tại quầy (tại cửa hàng): Khách thanh toán nốt tiền và nhận bánh ngay -> Trạng thái ĐÃ GIAO (4)
        // 2. Giao tận nơi: Khách thanh toán nốt tiền -> Trạng thái thanh toán là ĐÃ THANH TOÁN (1),
        //    nhưng đơn hàng vẫn là ĐANG GIAO (3 hoặc 7) chứ CHƯA GIAO XONG (chỉ chuyển thành 4 khi shipper giao thành công)
        $targetTrangThai = $isGiaoTanNoi
            ? (in_array((int)$o->trangThaiDonHang, [4, 5], true) ? 4 : 3)
            : 4;

        DB::beginTransaction();
        try {
            $daXuatKho = (int) ($o->daXuatKho ?? 0);
            if ($daXuatKho == 0) {
                $this->deductStockForOrder($o);
                $daXuatKho = 1;
            }

            $upd = [
                'trangThaiDonHang' => $targetTrangThai,
                'trangThaiThanhToan' => 1, // 1 = Đã thanh toán đủ
                'updatedAt' => now()
            ];
            if (Schema::hasColumn($donHangTable, 'daXuatKho')) {
                $upd['daXuatKho'] = $daXuatKho;
            }
            DB::table($donHangTable)->where('id', $o->id)->update($upd);

            if (DB::transactionLevel() > 0) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json(['success' => false, 'message' => 'Lỗi khi xử lý kho: ' . $e->getMessage()], 500);
        }

        // Tự động tạo đơn vận chuyển GHN nếu là giao tận nơi mà chưa có mã vận đơn
        $ghnMsg = '';
        if ($isGiaoTanNoi && empty($o->maVanDonGhn)) {
            $orderModel = \App\Models\DonHang::find($o->id);
            if ($orderModel) {
                $ghnRes = \App\Http\Controllers\Api\GhnController::createShippingOrderForDonHang($orderModel);
                if (!empty($ghnRes['order_code'])) {
                    $ghnMsg = " Đã tạo mã vận đơn GHN: {$ghnRes['order_code']}.";
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $isGiaoTanNoi
                ? "Thu tiền còn lại thành công! Đơn hàng đang được vận chuyển qua GHN.{$ghnMsg}"
                : "Hoàn tất thanh toán & giao bánh tại quầy thành công!"
        ]);
    }

    /**
     * Tạo vận đơn GHN thủ công cho đơn đặt trước
     */
    public function createGhnOrder(Request $request, $id)
    {
        [$donHangTable] = $this->getTables();
        $o = DB::table($donHangTable)->where('maDonHang', $id)->first();
        if (!$o) {
            return response()->json(['error' => 'Đơn không tồn tại'], 404);
        }

        $orderModel = \App\Models\DonHang::find($o->id);
        if (!$orderModel) {
            return response()->json(['error' => 'Không tìm thấy hóa đơn'], 404);
        }

        $toWardCode = $request->input('to_ward_code') ?? $orderModel->toWardCode;
        $toDistrictId = $request->input('to_district_id') ?? $orderModel->toDistrictId;

        $res = \App\Http\Controllers\Api\GhnController::createShippingOrderForDonHang($orderModel, $toWardCode, $toDistrictId);
        return response()->json($res);
    }
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi khi xử lý kho: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Hoàn tất thanh toán & giao bánh thành công!'
        ]);
    }
}
