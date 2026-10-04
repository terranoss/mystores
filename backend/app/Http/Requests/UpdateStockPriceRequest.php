<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_list' => 'sometimes|array|max:100',
            'stock_list.*.model_id' => 'sometimes|integer',
            'stock_list.*.seller_stock' => 'required_with:stock_list|array|max:10',
            'stock_list.*.seller_stock.*.stock' => 'required_with:stock_list|integer|min:0|max:999999',

            'price_list' => 'sometimes|array|max:100',
            'price_list.*.model_id' => 'sometimes|integer',
            'price_list.*.original_price' => 'required_with:price_list|numeric|min:0|max:100000000',

            'mode' => 'nullable|string|in:local,push',
        ];
    }
}
