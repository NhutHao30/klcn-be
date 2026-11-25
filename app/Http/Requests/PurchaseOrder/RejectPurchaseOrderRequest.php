<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class RejectPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('lyDoTuChoi')) {
            $reason = $this->input('lyDo') ?? $this->input('ly_do') ?? $this->input('reason');
            if ($reason !== null) {
                $this->merge(['lyDoTuChoi' => $reason]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'lyDoTuChoi' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lyDoTuChoi.required' => 'Vui lòng nhập lý do từ chối phiếu nhập hàng.',
            'lyDoTuChoi.min' => 'Lý do từ chối phải có ít nhất 3 ký tự.',
        ];
    }
}
