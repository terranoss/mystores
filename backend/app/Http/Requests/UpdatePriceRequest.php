<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_list' => 'required|array|max:100',
            'price_list.*.model_id' => 'sometimes|integer',
            'price_list.*.original_price' => 'required|numeric|min:0|max:100000000',
        ];
    }
}
