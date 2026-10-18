<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosModifierGroup extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    public function modifiers(): HasMany
    {
        return $this->hasMany(PosModifier::class, 'pos_group_id', 'pos_group_id');
    }
}
