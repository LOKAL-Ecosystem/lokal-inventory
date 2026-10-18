<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosModifier extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    protected $casts = [
        'price_delta' => 'integer',
    ];

    public function adjustments(): HasMany
    {
        return $this->hasMany(ModifierRecipeAdjustment::class, 'pos_modifier_id', 'pos_modifier_id');
    }
}
