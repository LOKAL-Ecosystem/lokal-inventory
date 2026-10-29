<?php

namespace App\Services;

use App\Models\Outlet;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Satu-satunya tempat yang tahu bentuk kontrak REST /api/v1 milik lokal_pos
 * (lihat WEBHOOK_CONTRACT.md v2.2 di repo lokal_pos). Perubahan kontrak cukup diubah di sini.
 */
class LokalPosClient
{
    /** Scope token API yang dibutuhkan lokal-inventory, beserta kegunaannya. */
    public const SCOPES = [
        'catalog:read' => 'Baca katalog (produk, kategori, opsi kustomisasi)',
        'orders:read' => 'Baca pesanan (cadangan payload webhook dan rekonsiliasi)',
        'catalog:availability' => 'Tandai menu habis / tersedia',
        'stock-alerts:write' => 'Kirim daftar bahan hampir habis ke lonceng POS',
    ];

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
            ->timeout((int) config('services.lokal_pos.timeout', 10))
            // Sertifikat lokal Herd: verifikasi SSL hanya boleh dimatikan saat APP_ENV=local.
            ->withOptions(['verify' => config('lokal.link.verify_ssl') || ! app()->environment('local')]);
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
     * Tandai menu habis / tersedia di POS (PATCH /products/{id}/availability, kontrak v2.1).
     * POS tidak menimpa tanda habis manual: lihat `applied` dan `availability_source` pada hasilnya.
     *
     * @return array{is_available: bool, availability_source: string|null, applied: bool}
     */
    public function setAvailability(int $posProductId, bool $available): array
    {
        $data = $this->data(
            $this->http()->patch("/products/{$posProductId}/availability", [
                'is_available' => $available,
                'source' => 'inventory',
            ])->throw()->json()
        );

        return [
            'is_available' => (bool) ($data['is_available'] ?? $available),
            'availability_source' => $data['availability_source'] ?? null,
            'applied' => (bool) ($data['applied'] ?? true),
        ];
    }

    /**
     * Ganti seluruh daftar peringatan stok outlet di POS (PUT /stock-alerts, kontrak v2.1).
     *
     * @param  array<int, array{key: string, name: string, current: float|int, min: float|int, unit: string}>  $alerts
     */
    public function replaceStockAlerts(array $alerts): void
    {
        $this->http()->put('/stock-alerts', ['alerts' => $alerts])->throw();
    }

    /**
     * Uji tiap scope tanpa mengubah data di POS. Hasil: scope => ['ok' => bool, 'message' => string].
     *
     * Scope baca dipanggil sungguhan. Dua scope tulis diuji dengan permintaan yang pasti ditolak
     * validasi/404 (produk 0, daftar bukan array), sehingga 403 berarti scope kurang dan 404/422
     * berarti scope ada.
     *
     * @return array<string, array{ok: bool, message: string}>
     */
    public function probeScopes(): array
    {
        $probes = [
            'catalog:read' => fn () => $this->http()->get('/catalog'),
            'orders:read' => fn () => $this->http()->get('/orders', ['per_page' => 1]),
            'catalog:availability' => fn () => $this->http()->patch('/products/0/availability', ['is_available' => true, 'source' => 'inventory']),
            'stock-alerts:write' => fn () => $this->http()->put('/stock-alerts', ['alerts' => 'probe']),
        ];

        $results = [];

        foreach ($probes as $scope => $probe) {
            try {
                $status = $probe()->status();
            } catch (\Throwable $e) {
                $results[$scope] = ['ok' => false, 'message' => 'Tidak bisa dihubungi: ' . $e->getMessage()];

                continue;
            }

            $results[$scope] = match (true) {
                $status === 401 => ['ok' => false, 'message' => 'API key ditolak (401)'],
                $status === 403 => ['ok' => false, 'message' => 'Scope belum diberikan ke token ini (403)'],
                in_array($status, [200, 404, 422], true) => ['ok' => true, 'message' => 'Scope tersedia'],
                default => ['ok' => false, 'message' => "Respons tak terduga ({$status})"],
            };
        }

        return $results;
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
