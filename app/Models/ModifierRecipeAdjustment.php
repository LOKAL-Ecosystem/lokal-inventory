<?php

namespace App\Models;

use App\Enums\RecipeAdjustmentAction;
use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierRecipeAdjustment extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    protected $casts = [
        'action' => RecipeAdjustmentAction::class,
        'qty' => 'decimal:3',
        'factor' => 'decimal:4',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function replacesIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'replaces_ingredient_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(PosModifier::class, 'pos_modifier_id', 'pos_modifier_id');
    }
}
