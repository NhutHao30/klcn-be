<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NguoiDung;
use App\Models\TaiKhoan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = NguoiDung::where('loaiNguoiDung', 0)
            ->with(['taikhoan', 'donhang'])
            ->get();

        return response()->json($customers->map(function ($c) {
            $totalOrders = $c->donhang ? $c->donhang->count() : 0;
            $totalSpent = $c->donhang ? $c->donhang->whereIn('trangThaiThanhToan', [1])->sum('tongThanhToan') : 0;

            $diemTichLuy = (int) floor($totalSpent / 10000);
            $rank = 'Đồng';
            if ($diemTichLuy >= 1000) {
                $rank = 'Thách đấu';
            } elseif ($diemTichLuy >= 500) {
                $rank = 'Kim cương';
            } elseif ($diemTichLuy >= 200) {
                $rank = 'Vàng';
            } elseif ($diemTichLuy >= 50) {
                $rank = 'Bạc';
            }

            return [
                'id' => $c->id,
                'maKH' => 'KH' . sprintf('%06d', $c->id),
                'hoTen' => $c->hoTen,
                'sdt' => $c->soDienThoai,
                'email' => $c->taikhoan ? $c->taikhoan->email : null,
                'ngaySinh' => $c->ngaySinh,
                'gioiTinh' => $c->gioiTinh,
                'diaChi' => $c->diachi->first()->diaChiChiTiet ?? null,
                'diemTichLuy' => $diemTichLuy,
                'membershipRank' => $rank,
                'totalOrders' => $totalOrders,
                'totalSpent' => (float) $totalSpent,
                'macuahang' => $c->cuaHangId,
            ];
        }));
    }

    public function store(Request $request)
    {
        $hoTen = $request->input('hoTen', $request->input('HoTen', $request->input('HOTEN')));
        $sdt = $request->input('sdt', $request->input('Sdt', $request->input('SDT')));
        $ngaySinh = $request->input('ngaySinh', $request->input('NGAYSINH'));
        $gioiTinh = $request->input('gioiTinh', $request->input('GioiTinh', $request->input('GIOITINH', 0)));
        $diaChi = $request->input('diaChi', $request->input('DIACHI'));

        if (!$hoTen || !$sdt) {
            return response()->json(['message' => 'Họ tên và Số điện thoại là bắt buộc.'], 422);
        }

        $existing = NguoiDung::where('soDienThoai', $sdt)->first();
        if ($existing) {
            return response()->json([
                'id' => $existing->id,
                'maKH' => 'KH' . sprintf('%06d', $existing->id),
                'hoTen' => $existing->hoTen,
                'sdt' => $existing->soDienThoai,
                'message' => 'Khách hàng với số điện thoại này đã tồn tại.'
            ], 200);
        }

        $maCuaHang = $request->input('macuahang', $request->input('MACUAHANG', 1));
        $user = Auth::guard('api')->user();
        if ($user) {
            $staffProfile = NguoiDung::where('taiKhoanId', $user->id)->first();
            if ($staffProfile && $staffProfile->cuaHangId) {
                $maCuaHang = $staffProfile->cuaHangId;
            }
        }

        return DB::transaction(function () use ($hoTen, $sdt, $ngaySinh, $gioiTinh, $diaChi, $maCuaHang) {
            $username = 'kh_' . preg_replace('/[^0-9]/', '', $sdt);
            if (TaiKhoan::where('username', $username)->exists()) {
                $username = $username . '_' . time();
            }
            $email = $username . '@customer.dolabakery.com';

            $taiKhoan = TaiKhoan::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('123456'),
                'vaiTroId' => 6, // CUSTOMER role
                'trangThai' => 1,
            ]);

            $nguoiDung = NguoiDung::create([
                'taiKhoanId' => $taiKhoan->id,
                'cuaHangId' => $maCuaHang,
                'hoTen' => $hoTen,
                'soDienThoai' => $sdt,
                'gioiTinh' => (int) $gioiTinh,
                'ngaySinh' => $ngaySinh ? date('Y-m-d', strtotime($ngaySinh)) : null,
                'loaiNguoiDung' => 0, // 0: Khách hàng
            ]);

            return response()->json([
                'id' => $nguoiDung->id,
                'maKH' => 'KH' . sprintf('%06d', $nguoiDung->id),
                'hoTen' => $nguoiDung->hoTen,
                'sdt' => $nguoiDung->soDienThoai,
                'email' => $taiKhoan->email,
                'diemTichLuy' => 0,
                'membershipRank' => 'Đồng',
            ]);
        });
    }

    public function update(Request $request, $id)
    {
        $kh = NguoiDung::find($id);
        if (!$kh) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $kh->update([
            'hoTen' => $request->hoTen ?? $request->HoTen ?? $kh->hoTen,
            'soDienThoai' => $request->sdt ?? $request->Sdt ?? $kh->soDienThoai,
            'ngaySinh' => $request->ngaySinh ?? $kh->ngaySinh,
            'gioiTinh' => $request->gioiTinh ?? $kh->gioiTinh,
            'cuaHangId' => $request->macuahang ?? $kh->cuaHangId
        ]);

        return response()->json($kh);
    }

    public function destroy($id)
    {
        $kh = NguoiDung::find($id);
        if ($kh) {
            $kh->delete();
        }
        return response()->json(['message' => 'Đã vô hiệu hóa khách hàng']);
    }

    public function restore($id)
    {
        $kh = NguoiDung::withTrashed()->find($id);
        if ($kh) {
            $kh->restore();
            return response()->json(['message' => 'Đã khôi phục tài khoản khách hàng']);
        }
        return response()->json(['error' => 'Không tìm thấy khách hàng'], 404);
    }

    public function lookupBySdt(Request $request)
    {
        $sdt = $request->input('sdt');
        if (!$sdt || strlen(trim($sdt)) < 3) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        $kh = NguoiDung::where('soDienThoai', 'LIKE', '%' . trim($sdt) . '%')->first();

        if (!$kh) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        $totalSpent = $kh->donhang ? $kh->donhang->whereIn('trangThaiThanhToan', [1])->sum('tongThanhToan') : 0;
        $diemTichLuy = (int) floor($totalSpent / 10000);

        return response()->json([
            'found' => true,
            'customer' => [
                'id'         => $kh->id,
                'maKH'       => $kh->id,
                'hoTen'      => $kh->hoTen,
                'sdt'        => $kh->soDienThoai,
                'diemTichLuy'=> $diemTichLuy,
            ],
        ]);
    }
}

