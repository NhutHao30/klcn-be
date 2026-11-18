<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemFeatureLock;
use Illuminate\Support\Facades\Auth;

class SystemFeatureController extends Controller
{
    /**
     * Get status of all system features (Public / All logged in users)
     */
    public function index()
    {
        $features = SystemFeatureLock::orderBy('nhomChucNang')->orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => $features
        ]);
    }

    /**
     * Toggle lock status of a feature (Admin only)
     */
    public function toggle(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user || ((int)$user->MAROLE !== 1 && (int)$user->vaiTroId !== 1)) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ tài khoản Admin (Quản trị viên) mới có quyền khóa/mở khóa chức năng hệ thống!'
            ], 403);
        }

        $request->validate([
            'maChucNang' => 'required|string|exists:he_thong_khoa_chuc_nang,maChucNang',
            'isLocked' => 'required|boolean',
            'lyDoKhoa' => 'nullable|string|max:255',
        ]);

        $feature = SystemFeatureLock::where('maChucNang', $request->maChucNang)->first();
        if (!$feature) {
            return response()->json(['success' => false, 'message' => 'Chức năng không tồn tại!'], 4404);
        }

        $feature->isLocked = (bool)$request->isLocked;
        $feature->lyDoKhoa = $request->isLocked ? ($request->lyDoKhoa ?: 'Bảo trì hệ thống') : null;
        $feature->updatedBy = $user->username ?: 'Admin';
        $feature->save();

        $actionText = $feature->isLocked ? 'Đã TẠM KHÓA' : 'Đã KÍCH HOẠT MỞ KHÓA';
        return response()->json([
            'success' => true,
            'message' => "{$actionText} chức năng \"{$feature->tenChucNang}\" thành công!",
            'data' => $feature
        ]);
    }
}
