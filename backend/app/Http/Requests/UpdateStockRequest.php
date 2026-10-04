<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_list' => 'required|array|max:100',
            'stock_list.*.model_id' => 'sometimes|integer',
            'stock_list.*.seller_stock' => 'required|array|max:10',
            'stock_list.*.seller_stock.*.stock' => 'required|integer|min:0|max:999999',
        ];
    }
}
