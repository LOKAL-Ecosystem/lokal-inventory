<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockReceiptRequest extends FormRequest
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
        $outletId = $this->user()->outlet_id;

        return [
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('outlet_id', $outletId)],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', Rule::exists('ingredients', 'id')->where('outlet_id', $outletId)],
            'items.*.purchase_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.total_price' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.ingredient_id' => 'bahan',
            'items.*.purchase_qty' => 'jumlah',
            'items.*.total_price' => 'harga',
            'received_at' => 'tanggal',
        ];
    }
}
