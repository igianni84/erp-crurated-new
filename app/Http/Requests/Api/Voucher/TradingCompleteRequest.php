<?php

namespace App\Http\Requests\Api\Voucher;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for trading completion callback.
 *
 * Validates the payload from external trading platforms when a trade is completed.
 */
class TradingCompleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled at the route/middleware level
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_customer_id' => ['required', 'integer', 'exists:customers,id'],
            'trading_reference' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_customer_id.required' => 'The new customer ID is required.',
            'new_customer_id.integer' => 'The new customer ID must be an integer.',
            'new_customer_id.exists' => 'The specified customer does not exist.',
            'trading_reference.required' => 'The trading reference is required.',
            'trading_reference.string' => 'The trading reference must be a string.',
            'trading_reference.max' => 'The trading reference must not exceed 255 characters.',
        ];
    }
}
