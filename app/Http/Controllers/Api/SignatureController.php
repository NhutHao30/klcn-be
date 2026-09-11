<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\NhanVien;
use App\Models\HdNhap;
use App\Models\BienBanHuy;

class SignatureController extends Controller
{
    /**
     * Cập nhật chữ ký số cá nhân trong Profile (Nhân viên / Quản lý)
     */
    public function updateProfileSignature(Request $request)
    {
        $request->validate([
            'chu_ky' => 'required|string',
        ]);

        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nhanVien = NhanVien::where('USERNAME', $user->USERNAME)->first();
        if (!$nhanVien) {
            return response()->json(['error' => 'Tài khoản không phải nhân viên hệ thống'], 403);
        }

        $nhanVien->CHU_KY = $request->chu_ky;
        $nhanVien->save();

        return response()->json([
            'status' => true,
            'message' => 'Lưu chữ ký số cá nhân thành công!',
            'chu_ky' => $nhanVien->CHU_KY
        ]);
    }

    /**
     * Lấy danh sách tài liệu chờ ký theo phân quyền của người dùng đăng nhập
     */
    public function getPendingSignatures(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nhanVien = NhanVien::where('USERNAME', $user->USERNAME)->first();
        $chucVu = $nhanVien ? mb_strtolower($nhanVien->CHUCVU ?? '') : '';
        $role = (int) $user->MAROLE;

        $macuahang = $nhanVien ? $nhanVien->MACUAHANG : null;

        // Query HD Nhập
        $queryHd = HdNhap::with(['nhanvien', 'nhacungcap', 'chitiets.sanpham']);
        if ($role === 1 && $macuahang) {
            $queryHd->where('MACUAHANG_NHAP', $macuahang);
        }

        // Query Biên Bản Hủy
        $queryHuy = BienBanHuy::with(['loHang.sanpham', 'cuahang', 'nhanvien']);
        if ($role === 1 && $macuahang) {
            $queryHuy->where('MACUAHANG', $macuahang);
        }

        $hdList = $queryHd->orderBy('created_at', 'desc')->get();
        $huyList = $queryHuy->orderBy('created_at', 'desc')->get();

        // Categorize pending vs signed
        $pendingHd = [];
        $signedHd = [];
        foreach ($hdList as $hd) {
            $isKeToan = str_contains($chucVu, 'kế toán') || str_contains($chucVu, 'ke toan');
            $isGiamDoc = str_contains($chucVu, 'giám đốc') || str_contains($chucVu, 'giam doc') || str_contains($chucVu, 'quản lý chi nhánh') || str_contains($chucVu, 'quan ly chi nhanh') || ($role === 1 && !$isKeToan) || ($role === 0 && !$isKeToan && !str_contains($chucVu, 'thủ kho'));
            $isThuKho = str_contains($chucVu, 'thủ kho') || str_contains($chucVu, 'thu kho') || str_contains($chucVu, 'người lập') || ($role === 2 && !$isKeToan);

            $needsMySign = false;

            // Kế toán cần ký nếu chưa có KY_KE_TOAN và chưa từng ký ô khác trên phiếu này
            if ($isKeToan && empty($hd->KY_KE_TOAN) && $hd->USERNAME !== $user->USERNAME && $hd->NGUOI_KY_GIAM_DOC !== $user->USERNAME) {
                $needsMySign = true;
            }

            // Giám đốc cần ký nếu chưa có KY_GIAM_DOC và chưa từng ký ô khác trên phiếu này
            if ($isGiamDoc && empty($hd->KY_GIAM_DOC) && $hd->USERNAME !== $user->USERNAME && $hd->NGUOI_KY_KE_TOAN !== $user->USERNAME) {
                $needsMySign = true;
            }

            // Thủ kho cần ký nếu chưa có KY_THU_KHO
            if ($isThuKho && empty($hd->KY_THU_KHO) && $hd->NGUOI_KY_KE_TOAN !== $user->USERNAME && $hd->NGUOI_KY_GIAM_DOC !== $user->USERNAME) {
                $needsMySign = true;
            }

            if ($needsMySign && $hd->TRANGTHAI_KY !== 'hoan_tat_ky') {
                $pendingHd[] = $hd;
            } else {
                $signedHd[] = $hd;
            }
        }

        $pendingHuy = [];
        $signedHuy = [];
        foreach ($huyList as $bb) {
            $isKeToan = str_contains($chucVu, 'kế toán') || str_contains($chucVu, 'ke toan');
            $isGiamDoc = str_contains($chucVu, 'giám đốc') || str_contains($chucVu, 'giam doc') || str_contains($chucVu, 'quản lý chi nhánh') || str_contains($chucVu, 'quan ly chi nhanh') || ($role === 1 && !$isKeToan) || ($role === 0 && !$isKeToan && !str_contains($chucVu, 'thủ kho'));
            $isThuKho = str_contains($chucVu, 'thủ kho') || str_contains($chucVu, 'thu kho') || str_contains($chucVu, 'người lập') || ($role === 2 && !$isKeToan);

            $needsMySign = false;

            if ($isKeToan && empty($bb->KY_KE_TOAN) && $bb->USERNAME !== $user->USERNAME && $bb->NGUOI_KY_GIAM_DOC !== $user->USERNAME) {
                $needsMySign = true;
            }

            if ($isGiamDoc && empty($bb->KY_GIAM_DOC) && $bb->USERNAME !== $user->USERNAME && $bb->NGUOI_KY_KE_TOAN !== $user->USERNAME) {
                $needsMySign = true;
            }

            if ($isThuKho && empty($bb->KY_THU_KHO) && $bb->NGUOI_KY_KE_TOAN !== $user->USERNAME && $bb->NGUOI_KY_GIAM_DOC !== $user->USERNAME) {
                $needsMySign = true;
            }

            if ($needsMySign && $bb->TRANGTHAI_KY !== 'hoan_tat_ky') {
                $pendingHuy[] = $bb;
            } else {
                $signedHuy[] = $bb;
            }
        }

        return response()->json([
            'status' => true,
            'total_pending' => count($pendingHd) + count($pendingHuy),
            'hd_nhap' => [
                'pending' => $pendingHd,
                'signed'  => $signedHd,
                'all'     => $hdList,
            ],
            'bien_ban_huy' => [
                'pending' => $pendingHuy,
                'signed'  => $signedHuy,
                'all'     => $huyList,
            ]
        ]);
    }

    /**
     * Xác nhận ký số vào tài liệu (Phiếu Nhập hoặc Biên Bản Hủy)
     */
    public function signDocument(Request $request)
    {
        $request->validate([
            'loai_phieu' => 'required|in:hdnhap,bien_ban_huy',
            'id'         => 'required',
            'vaitro_ky'  => 'required|in:thu_kho,ke_toan,giam_doc',
            'chu_ky'     => 'nullable|string',
        ]);

        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nhanVien = NhanVien::where('USERNAME', $user->USERNAME)->first();
        $chucVu = $nhanVien ? mb_strtolower($nhanVien->CHUCVU ?? '') : '';
        $role = (int) $user->MAROLE;
        $vaitro = $request->vaitro_ky;
        $username = $user->USERNAME;

        // Xác định vai trò chuyên môn thực tế dựa trên Chức Vụ
        $isKeToan = str_contains($chucVu, 'kế toán') || str_contains($chucVu, 'ke toan');
        $isGiamDoc = str_contains($chucVu, 'giám đốc') || str_contains($chucVu, 'giam doc') || str_contains($chucVu, 'quản lý chi nhánh') || str_contains($chucVu, 'quan ly chi nhanh') || ($role === 1 && !$isKeToan) || ($role === 0 && !$isKeToan && !str_contains($chucVu, 'thủ kho'));
        $isThuKho = str_contains($chucVu, 'thủ kho') || str_contains($chucVu, 'thu kho') || str_contains($chucVu, 'người lập') || ($role === 2 && !$isKeToan);

        // Phân quyền ký đúng vị trí chuyên môn
        if ($vaitro === 'ke_toan' && !$isKeToan) {
            return response()->json(['error' => 'Bạn không thuộc bộ phận Kế toán. Không thể ký thay vị trí Kế toán!'], 403);
        }
        if ($vaitro === 'giam_doc' && !$isGiamDoc) {
            return response()->json(['error' => 'Bạn không có chức vụ Giám đốc / Quản lý chi nhánh. Không thể ký phê duyệt vị trí này!'], 403);
        }
        if ($vaitro === 'thu_kho' && !$isThuKho) {
            return response()->json(['error' => 'Bạn không phải Thủ kho. Không thể ký vị trí Thủ kho / Người lập!'], 403);
        }

        // Kiểm tra không cho 1 người ký nhiều ô trên cùng một phiếu
        if ($request->loai_phieu === 'hdnhap') {
            $hd = HdNhap::where('MAHDNHAP', $request->id)->first();
            if (!$hd) return response()->json(['error' => 'Không tìm thấy Phiếu Nhập Kho'], 444);

            if ($vaitro !== 'thu_kho' && $hd->USERNAME === $username && !empty($hd->KY_THU_KHO)) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Người Lập / Thủ Kho trên phiếu này, không được ký thêm vị trí khác!'], 422);
            }
            if ($vaitro !== 'ke_toan' && $hd->NGUOI_KY_KE_TOAN === $username) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Kế Toán trên phiếu này, không được ký thêm vị trí khác!'], 422);
            }
            if ($vaitro !== 'giam_doc' && $hd->NGUOI_KY_GIAM_DOC === $username) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Giám Đốc trên phiếu này, không được ký thêm vị trí khác!'], 422);
            }
        } else {
            $bb = BienBanHuy::find($request->id);
            if (!$bb) return response()->json(['error' => 'Không tìm thấy Biên Bản Hủy'], 444);

            if ($vaitro !== 'thu_kho' && $bb->USERNAME === $username && !empty($bb->KY_THU_KHO)) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Người Lập / Thủ Kho trên biên bản này, không được ký thêm vị trí khác!'], 422);
            }
            if ($vaitro !== 'ke_toan' && $bb->NGUOI_KY_KE_TOAN === $username) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Kế Toán trên biên bản này, không được ký thêm vị trí khác!'], 422);
            }
            if ($vaitro !== 'giam_doc' && $bb->NGUOI_KY_GIAM_DOC === $username) {
                return response()->json(['error' => 'Bạn đã ký ở vị trí Giám Đốc trên biên bản này, không được ký thêm vị trí khác!'], 422);
            }
        }

        // Lấy chữ ký: ưu tiên chữ ký gửi lên, nếu không có thì lấy chữ ký cá nhân đã lưu trong Profile
        $signatureToUse = $request->chu_ky ?: ($nhanVien ? $nhanVien->CHU_KY : null);
        if (!$signatureToUse) {
            return response()->json(['error' => 'Tài khoản chưa lưu chữ ký số trong Thông Tin Cá Nhân. Vui lòng tạo chữ ký số trước khi ký!'], 422);
        }

        if ($request->loai_phieu === 'hdnhap') {
            $hd = HdNhap::where('MAHDNHAP', $request->id)->first();
            if ($vaitro === 'ke_toan') {
                $hd->KY_KE_TOAN = $signatureToUse;
                $hd->NGUOI_KY_KE_TOAN = $user->USERNAME;
            } elseif ($vaitro === 'giam_doc') {
                $hd->KY_GIAM_DOC = $signatureToUse;
                $hd->NGUOI_KY_GIAM_DOC = $user->USERNAME;
            } elseif ($vaitro === 'thu_kho') {
                $hd->KY_THU_KHO = $signatureToUse;
            }

            if (!empty($hd->KY_THU_KHO) && !empty($hd->KY_NCC) && !empty($hd->KY_KE_TOAN) && !empty($hd->KY_GIAM_DOC)) {
                $hd->TRANGTHAI_KY = 'hoan_tat_ky';
            } else {
                $hd->TRANGTHAI_KY = 'da_ky_mot_phan';
            }
            $hd->save();

            return response()->json([
                'status' => true,
                'message' => 'Đã ký xác nhận Phiếu Nhập Kho thành công!',
                'data' => $hd
            ]);

        } else {
            $bb = BienBanHuy::find($request->id);
            if ($vaitro === 'ke_toan') {
                $bb->KY_KE_TOAN = $signatureToUse;
                $bb->NGUOI_KY_KE_TOAN = $user->USERNAME;
            } elseif ($vaitro === 'giam_doc') {
                $bb->KY_GIAM_DOC = $signatureToUse;
                $bb->NGUOI_KY_GIAM_DOC = $user->USERNAME;
            } elseif ($vaitro === 'thu_kho') {
                $bb->KY_THU_KHO = $signatureToUse;
            }

            if (!empty($bb->KY_THU_KHO) && !empty($bb->KY_KE_TOAN) && !empty($bb->KY_GIAM_DOC)) {
                $bb->TRANGTHAI_KY = 'hoan_tat_ky';
            } else {
                $bb->TRANGTHAI_KY = 'da_ky_mot_phan';
            }
            $bb->save();

            return response()->json([
                'status' => true,
                'message' => 'Đã ký xác nhận Biên Bản Hủy thành công!',
                'data' => $bb
            ]);
        }
    }
}
