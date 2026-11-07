<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DiaChi;
use App\Models\NguoiDung;

class AddressController extends Controller
{
    /**
     * Resolve current user's NguoiDung profile
     */
    private function getNguoiDung()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;

        $nd = $user->nguoidung;
        if (!$nd) {
            $nd = NguoiDung::firstOrCreate(
                ['taiKhoanId' => $user->id],
                [
                    'hoTen' => $user->username ?? 'Khách hàng',
                    'soDienThoai' => '0900000000',
                    'loaiNguoiDung' => 0,
                ]
            );
        }
        return $nd;
    }

    /**
     * Get all saved addresses of current customer
     */
    public function index()
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $addresses = DiaChi::where('nguoidungId', $nd->id)
            ->orderBy('isDefault', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    /**
     * Store new customer address
     */
    public function store(Request $request)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $request->validate([
            'diaChiChiTiet' => 'required|string|max:255',
            'hoTenNguoiNhan' => 'nullable|string|max:100',
            'soDienThoai' => 'nullable|string|max:20',
            'maTinh' => 'nullable|integer',
            'maHuyen' => 'nullable|integer',
            'maXa' => 'nullable|string',
            'isDefault' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('isDefault');
        // If this is the user's first address, automatically make it default
        $count = DiaChi::where('nguoidungId', $nd->id)->count();
        if ($count === 0) {
            $isDefault = true;
        }

        if ($isDefault) {
            DiaChi::where('nguoidungId', $nd->id)->update(['isDefault' => 0]);
        }

        $addr = DiaChi::create([
            'nguoidungId' => $nd->id,
            'hoTenNguoiNhan' => $request->input('hoTenNguoiNhan') ?? $nd->hoTen,
            'soDienThoai' => $request->input('soDienThoai') ?? $nd->soDienThoai,
            'diaChiChiTiet' => trim($request->input('diaChiChiTiet')),
            'maTinh' => (int)($request->input('maTinh') ?? 0),
            'maHuyen' => (int)($request->input('maHuyen') ?? 0),
            'maXa' => (string)($request->input('maXa') ?? ''),
            'isDefault' => $isDefault ? 1 : 0,
        ]);

        return response()->json([
            'message' => 'Lưu địa chỉ thành công!',
            'data' => $addr
        ], 201);
    }

    /**
     * Update existing address
     */
    public function update(Request $request, $id)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $addr = DiaChi::where('nguoidungId', $nd->id)->where('id', $id)->first();
        if (!$addr) {
            return response()->json(['error' => 'Không tìm thấy địa chỉ'], 404);
        }

        $isDefault = $request->boolean('isDefault');
        if ($isDefault) {
            DiaChi::where('nguoidungId', $nd->id)->where('id', '!=', $id)->update(['isDefault' => 0]);
        }

        $addr->update([
            'hoTenNguoiNhan' => $request->input('hoTenNguoiNhan') ?? $addr->hoTenNguoiNhan,
            'soDienThoai' => $request->input('soDienThoai') ?? $addr->soDienThoai,
            'diaChiChiTiet' => $request->input('diaChiChiTiet') ? trim($request->input('diaChiChiTiet')) : $addr->diaChiChiTiet,
            'maTinh' => $request->has('maTinh') ? (int)$request->input('maTinh') : $addr->maTinh,
            'maHuyen' => $request->has('maHuyen') ? (int)$request->input('maHuyen') : $addr->maHuyen,
            'maXa' => $request->has('maXa') ? (string)$request->input('maXa') : $addr->maXa,
            'isDefault' => $request->has('isDefault') ? ($isDefault ? 1 : 0) : $addr->isDefault,
        ]);

        return response()->json([
            'message' => 'Cập nhật địa chỉ thành công!',
            'data' => $addr
        ]);
    }

    /**
     * Delete an address
     */
    public function destroy($id)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $addr = DiaChi::where('nguoidungId', $nd->id)->where('id', $id)->first();
        if (!$addr) {
            return response()->json(['error' => 'Không tìm thấy địa chỉ'], 404);
        }

        $wasDefault = $addr->isDefault;
        $addr->delete();

        // If the deleted address was default, promote another address to default
        if ($wasDefault) {
            $another = DiaChi::where('nguoidungId', $nd->id)->orderBy('id', 'desc')->first();
            if ($another) {
                $another->update(['isDefault' => 1]);
            }
        }

        return response()->json(['message' => 'Đã xóa địa chỉ']);
    }

    /**
     * Set address as default
     */
    public function setDefault($id)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $addr = DiaChi::where('nguoidungId', $nd->id)->where('id', $id)->first();
        if (!$addr) {
            return response()->json(['error' => 'Không tìm thấy địa chỉ'], 404);
        }

        DiaChi::where('nguoidungId', $nd->id)->update(['isDefault' => 0]);
        $addr->update(['isDefault' => 1]);

        return response()->json([
            'message' => 'Đã đặt làm địa chỉ mặc định!',
            'data' => $addr
        ]);
    }

    /**
     * Get default address
     */
    public function getDefault()
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $addr = DiaChi::where('nguoidungId', $nd->id)->where('isDefault', 1)->first()
            ?? DiaChi::where('nguoidungId', $nd->id)->orderBy('id', 'desc')->first();

        return response()->json([
            'success' => true,
            'data' => $addr
        ]);
    }
}
