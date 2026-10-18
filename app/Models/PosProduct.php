<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cache produk dari lokal_pos. Jangan diedit dari repo ini selain kolom milik inventory.
 */
class PosProduct extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    protected $casts = [
        'price' => 'integer',
        'is_available' => 'boolean',
        'stock_sufficient' => 'boolean',
        'auto_unavailable' => 'boolean',
        'synced_at' => 'datetime',
        'last_missing_recipe_at' => 'datetime',
    ];

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'pos_product_id', 'pos_product_id');
    }
}
