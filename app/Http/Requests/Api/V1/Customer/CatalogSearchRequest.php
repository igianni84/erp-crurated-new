<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CatalogSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'country' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],
            'producer' => ['sometimes', 'string', 'max:200'],
            'appellation' => ['sometimes', 'string', 'max:200'],
            'vintage_min' => ['sometimes', 'integer', 'min:1900', 'max:2100'],
            'vintage_max' => ['sometimes', 'integer', 'min:1900', 'max:2100'],
            'format' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:vintage_asc,vintage_desc,name_asc,name_desc,newest'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'A search query is required.',
            'q.min' => 'The search query must be at least 2 characters.',
            'q.max' => 'The search query must not exceed 200 characters.',
            'sort.in' => 'Sort must be one of: vintage_asc, vintage_desc, name_asc, name_desc, newest.',
        ];
    }
}
