<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Outlet extends Model
{
    protected $guarded = [];

    protected $hidden = ['pos_api_key', 'webhook_secret'];

    protected static function booted(): void
    {
        static::creating(function (Outlet $outlet) {
            $outlet->webhook_token ??= Str::random(40);
        });
    }

    /**
     * URL penerima webhook unik outlet ini: outlet ditemukan dari token di URL (satu query),
     * lalu signature diverifikasi dengan secret outlet itu saja.
     */
    public function webhookUrl(): string
    {
        return route('webhooks.lokal-pos.outlet', $this->webhook_token);
    }

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
