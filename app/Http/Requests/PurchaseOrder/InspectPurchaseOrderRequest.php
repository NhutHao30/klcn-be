<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class InspectPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.chiTietId' => ['nullable', 'integer'],
            'items.*.loHangId' => ['nullable', 'integer'],
            'items.*.sanPhamId' => ['nullable', 'integer'],
            'items.*.soLuongThucNhan' => ['required', 'integer', 'min:0'],
            'items.*.ghiChuChenhLech' => ['nullable', 'string', 'max:500'],
            'ghiChu' => ['nullable', 'string', 'max:500'],
        ];
    }
}
