<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateShippingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'voucher_ids' => 'required|array|min:1',
            'voucher_ids.*' => 'required|uuid|exists:vouchers,id',
            'shipping_address_id' => 'required|uuid|exists:addresses,id',
            'special_instructions' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voucher_ids.required' => 'At least one voucher is required.',
            'voucher_ids.*.exists' => 'One or more vouchers were not found.',
            'shipping_address_id.required' => 'A shipping address is required.',
            'shipping_address_id.exists' => 'The selected shipping address was not found.',
        ];
    }
}
