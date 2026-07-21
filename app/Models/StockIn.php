<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'supplier_id',
        'user_id',
        'transaction_date',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_cost' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockInItem::class);
    }
}
