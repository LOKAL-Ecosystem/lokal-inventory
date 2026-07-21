<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'unit_id',
        'supplier_id',
        'quantity_on_hand',
        'minimum_stock',
        'unit_cost',
        'is_active',
        'pos_product_id',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->minimum_stock;
    }
}
