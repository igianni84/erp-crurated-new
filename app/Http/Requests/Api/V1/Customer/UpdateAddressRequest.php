<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\Customer\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', new Enum(AddressType::class)],
            'line_1' => 'sometimes|string|max:255',
            'line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:2',
            'is_default' => 'boolean',
        ];
    }
}
