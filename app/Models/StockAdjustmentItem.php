<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'system_quantity',
        'actual_quantity',
        'difference_quantity',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'actual_quantity' => 'decimal:2',
        'difference_quantity' => 'decimal:2',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
