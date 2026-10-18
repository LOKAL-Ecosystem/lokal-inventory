<?php

namespace App\Http\Requests;

use App\Enums\BaseUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = $this->route('ingredient')?->id;

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('ingredients', 'name')->where('outlet_id', $this->user()->outlet_id)->ignore($ignore),
            ],
            'category_name' => ['nullable', 'string', 'max:80'],
            'base_unit' => ['required', Rule::enum(BaseUnit::class)],
            'purchase_unit' => ['nullable', 'string', 'max:40'],
            'purchase_unit_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
