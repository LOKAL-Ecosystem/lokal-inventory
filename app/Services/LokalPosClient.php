<?php

namespace App\Services;

use App\Models\Outlet;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Satu-satunya tempat yang tahu bentuk kontrak REST /api/v1 milik lokal_pos
 * (lihat WEBHOOK_CONTRACT.md di repo lokal_pos). Perubahan kontrak cukup diubah di sini.
 */
class LokalPosClient
{
    public function __construct(protected Outlet $outlet)
    {
    }

    public static function for(Outlet $outlet): self
    {
        return new self($outlet);
    }

    protected function http(): PendingRequest
    {
        if (! $this->outlet->hasPosConnection()) {
            throw new RuntimeException('Koneksi ke Lokal POS belum diatur (URL / API key kosong).');
        }

        return Http::baseUrl(rtrim($this->outlet->pos_base_url, '/') . '/api/v1')
            ->withHeaders(['X-Api-Key' => $this->outlet->pos_api_key])
            ->acceptJson()
            ->timeout((int) config('services.lokal_pos.timeout', 10));
    }

    /**
     * @return array<string, mixed> isi "data" dari GET /catalog
     */
    public function catalog(): array
    {
        return $this->data($this->http()->get('/catalog')->throw()->json());
    }

    /**
     * @return array<string, mixed> isi "data" dari GET /orders/{uuid}
     */
    public function order(string $uuid): array
    {
        return $this->data($this->http()->get("/orders/{$uuid}")->throw()->json());
    }

    /**
     * @param  array<string, mixed>  $filters  status, since, date, per_page, page
     * @return array<int, array<string, mixed>>
     */
    public function orders(array $filters = []): array
    {
        return $this->data($this->http()->get('/orders', $filters)->throw()->json());
    }

    /**
     * Tandai menu habis / tersedia di POS. Endpoint ini belum ada di kontrak v2, jadi
     * hanya berjalan bila services.lokal_pos.availability_endpoint diisi.
     */
    public function setAvailability(int $posProductId, bool $available): bool
    {
        $endpoint = config('services.lokal_pos.availability_endpoint');

        if (blank($endpoint)) {
            return false;
        }

        $this->http()
            ->patch(str_replace('{id}', (string) $posProductId, $endpoint), [
                'is_available' => $available,
                'source' => 'inventory',
            ])
            ->throw();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(?array $response): array
    {
        if (! is_array($response) || ! array_key_exists('data', $response)) {
            throw new RuntimeException('Respons Lokal POS tidak sesuai kontrak (tidak ada "data").');
        }

        return $response['data'] ?? [];
    }
}
