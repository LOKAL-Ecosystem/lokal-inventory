<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_product_id',
        'pos_product_name',
        'stock_item_id',
        'quantity_needed',
        'unit',
    ];

    protected $casts = [
        'quantity_needed' => 'float',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'stock_item_id');
    }
}
