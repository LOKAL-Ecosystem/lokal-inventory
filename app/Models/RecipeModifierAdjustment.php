<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeModifierAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_product_id',
        'pos_modifier_id',
        'pos_modifier_name',
        'stock_item_id',
        'adjustment_type',
        'adjustment_qty',
        'unit',
    ];

    protected $casts = [
        'pos_product_id' => 'integer',
        'pos_modifier_id' => 'integer',
        'adjustment_qty' => 'float',
    ];

    /**
     * Relationship to raw ingredient Stock Item (Item model)
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'stock_item_id');
    }

    /**
     * Scope query to filter by pos_product_id
     */
    public function scopeForPosProduct(Builder $query, int|string $posProductId): Builder
    {
        return $query->where('pos_product_id', $posProductId);
    }
}
