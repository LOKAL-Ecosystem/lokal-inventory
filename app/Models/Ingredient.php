<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use BelongsToOutlet;

    protected $guarded = ['current_stock', 'avg_cost'];

    protected $casts = [
        'base_unit' => BaseUnit::class,
        'purchase_unit_qty' => 'decimal:3',
        'avg_cost' => 'decimal:4',
        'min_stock' => 'decimal:3',
        'current_stock' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereColumn('current_stock', '<=', 'min_stock');
    }

    public function isNegative(): bool
    {
        return (float) $this->current_stock < 0;
    }

    public function isLow(): bool
    {
        return (float) $this->current_stock <= (float) $this->min_stock;
    }

    /**
     * Angka stok selalu bersama satuannya, mis. "1.250 g".
     */
    public function formatQty(float|string|null $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', '.'), '0'), ',') . ' ' . $this->base_unit->value;
    }
}
