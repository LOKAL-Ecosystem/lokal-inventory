<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $guarded = [];

    protected $hidden = ['pos_api_key', 'webhook_secret'];

    protected $casts = [
        'pos_api_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'last_catalog_sync_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPosConnection(): bool
    {
        return filled($this->pos_base_url) && filled($this->pos_api_key);
    }

    /**
     * Rahasia tidak pernah ditampilkan utuh: hanya 4 karakter terakhir.
     */
    public static function mask(?string $secret): ?string
    {
        if (blank($secret)) {
            return null;
        }

        return str_repeat('•', 8) . substr($secret, -4);
    }
}
