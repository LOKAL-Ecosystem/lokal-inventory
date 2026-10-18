<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientCategory extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
