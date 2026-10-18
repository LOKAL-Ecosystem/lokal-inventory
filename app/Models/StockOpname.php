<?php

namespace App\Models;

use App\Enums\OpnameStatus;
use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    use BelongsToOutlet;

    protected $guarded = [];

    protected $casts = [
        'opname_date' => 'date',
        'status' => OpnameStatus::class,
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === OpnameStatus::Completed;
    }
}
