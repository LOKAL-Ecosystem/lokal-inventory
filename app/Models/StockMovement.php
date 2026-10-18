<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger append-only. Koreksi selalu lewat pergerakan baru, dan semua penulisan
 * harus lewat App\Services\StockService.
 */
class StockMovement extends Model
{
    use BelongsToOutlet;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'type' => StockMovementType::class,
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('stock_movements bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('stock_movements bersifat append-only.'));
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reasonLabel(): ?string
    {
        return $this->reason ? WasteReason::tryFrom($this->reason)?->label() : null;
    }
}
