<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnmappedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_product_id',
        'product_name',
        'last_transaction_id',
        'occurrence_count',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}
