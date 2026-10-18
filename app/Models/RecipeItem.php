<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PosProduct::class, 'pos_product_id', 'pos_product_id');
    }
}
