<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PurchaseOrderSignatureRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SignPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('vaiTroKy') && $this->has('vai_tro_ky')) {
            $this->merge(['vaiTroKy' => $this->input('vai_tro_ky')]);
        }
        if (!$this->has('tenNguoiKy')) {
            $ten = $this->input('ten_nguoi_ky') ?? $this->input('ten_nguoi_nhan');
            if ($ten !== null) {
                $this->merge(['tenNguoiKy' => $ten]);
            }
        }
        if (!$this->has('chuKyAnh')) {
            $sig = $this->input('chu_ky_anh') ?? $this->input('signature_data') ?? $this->input('ky_nguoi_nhan');
            if ($sig !== null) {
                $this->merge(['chuKyAnh' => $sig]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'vaiTroKy' => ['required', new Enum(PurchaseOrderSignatureRole::class)],
            'tenNguoiKy' => ['nullable', 'string', 'max:255'],
            'chuKyAnh' => ['nullable', 'string'],
            'ghiChu' => ['nullable', 'string', 'max:500'],
        ];
    }
}
