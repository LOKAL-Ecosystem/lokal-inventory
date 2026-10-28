<?php

namespace Database\Seeders;

use App\Enums\WasteReason;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\PosModifier;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WebhookInbox;
use App\Services\MenuAvailabilityService;
use App\Services\OpnameService;
use App\Services\StockDeductionService;
use App\Services\StockReceiptService;
use App\Services\StockService;
use App\Support\CurrentOutlet;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Riwayat stok demo untuk outlet Lokal Coffee: tiga minggu penjualan dari POS (potong stok,
 * sebagian di-void), belanja rutin per supplier dengan harga yang naik-turun, waste harian,
 * dua opname selesai, dan satu opname draft hari ini.
 *
 * Semua lewat Service (StockReceiptService, StockDeductionService, StockService, OpnameService),
 * jadi ledger, current_stock, avg_cost, dan ketersediaan menu persis seperti buatan aplikasi.
 * Waktu dipalsukan per kejadian supaya tanggal tersebar. Dilewati bila outlet sudah punya pergerakan stok.
 *
 * Skenario yang sengaja dibuat untuk dashboard: bahan hampir habis (Boba, Sirup Hazelnut, Whipped Cream),
 * stok negatif (Whipped Cream), menu habis karena stok (Taro Latte), menu tanpa resep (Lemon Tea),
 * dan satu webhook gagal diproses.
 *
 * Catatan: pesanan di sini tidak sama dengan pesanan demo lokal_pos (uuid acak), jadi rekonsiliasi
 * dengan POS sungguhan akan menambah pesanan POS sebagai penjualan baru.
 */
class InventoryDemoSeeder extends Seeder
{
    protected const DAYS = 21;

    /** Hari (mundur dari hari ini) dengan belanja rutin; hari pertama = stok awal. */
    protected const RESTOCK_DAYS = [21, 18, 15, 12, 9, 6, 3];

    /** Bobot jam pesanan 07.00-20.59. */
    protected const HOURS = [7 => 3, 8 => 6, 9 => 6, 10 => 5, 11 => 5, 12 => 8, 13 => 7, 14 => 5, 15 => 6, 16 => 7, 17 => 6, 18 => 6, 19 => 7, 20 => 5];

    /** Popularitas produk (id POS => bobot). Produk habis manual (30, 31) tidak dijual. */
    protected const POPULARITY = [
        1 => 3, 2 => 8, 3 => 12, 4 => 6, 5 => 14, 6 => 5, 7 => 4, 8 => 6, 9 => 4, 10 => 6,
        11 => 3, 12 => 5, 13 => 3, 14 => 2, 15 => 5, 16 => 2, 17 => 3, 18 => 5, 19 => 3, 20 => 4,
        21 => 3, 22 => 3, 23 => 2, 24 => 3, 25 => 2, 26 => 4, 27 => 6, 28 => 3, 29 => 3,
    ];

    /** Kejadian waste yang biasa terjadi: [bahan, min, maks, alasan, catatan]. */
    protected const WASTE = [
        ['Susu Sapi', 100, 400, WasteReason::Spilled, 'Tumpah saat steaming'],
        ['Croissant Frozen', 1, 2, WasteReason::Expired, 'Lewat sehari di etalase'],
        ['Biji Kopi', 18, 36, WasteReason::WrongOrder, 'Salah buat pesanan'],
        ['Alpukat', 200, 400, WasteReason::Damaged, 'Alpukat busuk'],
        ['Es Batu', 1000, 2500, WasteReason::Damaged, 'Mencair di freezer yang sempat mati'],
        ['Cup Plastik 16oz', 2, 5, WasteReason::Damaged, 'Cup retak'],
        ['Pisang', 1, 3, WasteReason::Expired, 'Terlalu matang'],
        ['Brownies Potong', 1, 2, WasteReason::Expired, 'Lewat 3 hari'],
        ['Oat Milk', 100, 250, WasteReason::WrongOrder, 'Pelanggan minta ganti susu sapi'],
    ];

    /** Hasil hitung fisik opname terakhir yang menciptakan skenario dashboard. */
    protected const OPNAME_OVERRIDES = [
        'Bubuk Taro' => 20,          // < 30 g per porsi: Taro Latte habis karena stok
        'Whipped Cream' => 40,       // pesanan hari ini membuatnya negatif
        'Boba' => 560,               // penjualan hari ini membawanya di bawah minimum 500 g
        'Sirup Hazelnut' => 260,     // di bawah minimum 300 ml
    ];

    protected Carbon $realNow;
    protected Carbon $today;
    /** @var Collection<string, User> */
    protected Collection $users;
    /** @var Collection<string, Supplier> */
    protected Collection $suppliers;
    protected int $itemSeq = 0;
    protected int $orders = 0;
    protected int $voids = 0;

    public function run(): void
    {
        $outlet = Outlet::where('name', 'Lokal Coffee')->first();

        if (! $outlet) {
            return;
        }

        app(CurrentOutlet::class)->run($outlet, function () {
            if (StockMovement::exists()) {
                $this->command?->info('Riwayat stok demo dilewati: outlet sudah punya pergerakan stok.');

                return;
            }

            // Job ke POS hanya berjalan bila outlet terhubung; tetap sinkron supaya tabel jobs tidak terisi ribuan baris.
            config(['queue.default' => 'sync']);
            mt_srand(20261028);

            $this->realNow = now()->copy();
            $this->today = $this->realNow->copy()->startOfDay();
            $this->users = User::where('outlet_id', app(CurrentOutlet::class)->id())->get()->keyBy('email');
            $this->suppliers = Supplier::all()->keyBy('name');

            try {
                $this->seedHistory();
            } finally {
                Carbon::setTestNow();
            }

            app(MenuAvailabilityService::class)->refreshAll();

            $this->command?->info(sprintf(
                'Riwayat stok demo: %d pesanan (%d di-void), %d pergerakan stok.',
                $this->orders, $this->voids, StockMovement::count()
            ));
        });
    }

    protected function seedHistory(): void
    {
        for ($daysAgo = self::DAYS; $daysAgo >= 1; $daysAgo--) {
            $day = $this->today->copy()->subDays($daysAgo);

            if (in_array($daysAgo, self::RESTOCK_DAYS, true)) {
                $this->restock($day, $daysAgo);
            }

            $this->sellDay($day);
            $this->recordWaste($day);

            if ($daysAgo === 8) {
                $this->completedOpname($day->copy()->setTime(21, 30), 'Opname mingguan', []);
            }
        }

        $this->completedOpname($this->today->copy()->subDay()->setTime(21, 40), 'Opname akhir minggu', self::OPNAME_OVERRIDES);

        // Hari ini: event katalog, penjualan sampai sekarang (dengan inbox webhook), opname bar yang belum selesai.
        $this->catalogEvent($this->clampToNow($this->today->copy()->setTime(7, 5)));
        $this->sellDay($this->today, withInbox: true);
        $this->forcedOrders();
        $this->failedWebhook();
        $this->draftOpname();
    }

    protected function restock(Carbon $day, int $daysAgo): void
    {
        $initial = $daysAgo === self::DAYS;
        $minute = 0;

        $ingredients = Ingredient::where('is_active', true)->get()
            ->groupBy(fn (Ingredient $i) => DatabaseSeeder::INGREDIENTS[$i->name][6]);

        foreach ($ingredients as $supplierName => $group) {
            $items = [];

            foreach ($group as $ingredient) {
                $stock = (float) $ingredient->current_stock;
                $min = (float) $ingredient->min_stock;
                $perUnit = (float) $ingredient->purchase_unit_qty;

                if (! $initial && $stock >= $min * 3) {
                    continue;
                }

                $units = max(1, (int) ceil(($min * ($initial ? 4 : 6) - $stock) / $perUnit));
                $unitPrice = $this->price($ingredient->name, $daysAgo);
                $items[] = ['ingredient_id' => $ingredient->id, 'purchase_qty' => $units, 'total_price' => $unitPrice * $units];
            }

            if ($items === []) {
                continue;
            }

            $this->at($day->copy()->setTime(8, 0)->addMinutes($minute += mt_rand(4, 15)));
            app(StockReceiptService::class)->create(
                [
                    'supplier_id' => $this->suppliers[$supplierName]->id,
                    'received_at' => $day->toDateString(),
                    'note' => $initial ? 'Stok awal' : ($supplierName === 'Pasar Induk - Bu Ani' ? 'Belanja pasar pagi' : 'Belanja rutin'),
                ],
                $items,
                $this->user($initial ? 'admin@lokal.com' : null)->id,
            );
        }
    }

    /**
     * Harga per satuan beli: harga dasar + tren (susu & kopi naik, alpukat naik-turun) + sedikit acak, dibulatkan Rp 500.
     */
    protected function price(string $ingredient, int $daysAgo): int
    {
        $base = DatabaseSeeder::INGREDIENTS[$ingredient][5];
        $trend = match ($ingredient) {
            'Susu Sapi', 'Oat Milk' => $daysAgo <= 10 ? 1.08 : 1.0,
            'Biji Kopi' => $daysAgo <= 7 ? 1.05 : 1.0,
            'Alpukat' => 1 + (($daysAgo % 6) - 3) * 0.05,
            default => 1.0,
        };

        return (int) (round($base * $trend * (1 + mt_rand(-30, 30) / 1000) / 500) * 500);
    }

    protected function sellDay(Carbon $day, bool $withInbox = false): void
    {
        $count = match ($day->dayOfWeekIso) {
            6, 7 => 70,
            5 => 55,
            default => 45,
        } + mt_rand(-8, 8);

        $times = collect(range(1, $count))
            ->map(fn () => $day->copy()->setTime($this->weighted(self::HOURS), mt_rand(0, 59), mt_rand(0, 59)))
            ->sort()
            ->values();

        foreach ($times as $time) {
            if ($time->greaterThan($this->realNow->copy()->subMinutes(5))) {
                break;
            }

            $this->placeOrder($time, $this->randomItems(), $withInbox);
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, modifier_ids: array<int>}>  $lines
     */
    protected function placeOrder(Carbon $time, array $lines, bool $withInbox): void
    {
        if ($lines === []) {
            return;
        }

        $this->at($time);
        $uuid = (string) Str::uuid();
        $items = [];

        foreach ($lines as $line) {
            [$name, , $price] = DatabaseSeeder::PRODUCTS[$line['product_id']];
            $items[] = [
                'item_id' => ++$this->itemSeq,
                'product_id' => $line['product_id'],
                'name' => $name,
                'price' => $price,
                'quantity' => $line['quantity'],
                'modifier_ids' => $line['modifier_ids'],
            ];
        }

        app(StockDeductionService::class)->deductOrder(['uuid' => $uuid, 'status' => 'confirmed', 'items' => $items]);
        $this->orders++;

        if ($withInbox) {
            $this->inbox('order.confirmed', $uuid, $items, 'confirmed');
        }

        // Sekitar 2% pesanan di-void beberapa menit setelah lunas.
        if (mt_rand(1, 100) <= 2) {
            $voidAt = $time->copy()->addMinutes(mt_rand(5, 40));

            if ($voidAt->lessThan($this->realNow)) {
                $this->at($voidAt);
                app(StockDeductionService::class)->returnOrder($uuid);
                $this->voids++;

                if ($withInbox) {
                    $this->inbox('order.voided', $uuid, $items, 'voided');
                }
            }
        }
    }

    /**
     * @return array<int, array{product_id: int, quantity: int, modifier_ids: array<int>}>
     */
    protected function randomItems(): array
    {
        $unavailable = PosProduct::where('auto_unavailable', true)->pluck('pos_product_id')->all();
        $popularity = array_diff_key(self::POPULARITY, array_flip($unavailable));
        $lines = [];

        for ($n = $this->weighted([1 => 55, 2 => 30, 3 => 12, 4 => 3]); $n > 0; $n--) {
            $productId = $this->weighted($popularity);
            $lines[] = [
                'product_id' => $productId,
                'quantity' => mt_rand(1, 100) <= 85 ? 1 : 2,
                'modifier_ids' => $this->randomModifiers($productId),
            ];
        }

        return $lines;
    }

    /**
     * @return array<int>
     */
    protected function randomModifiers(int $productId): array
    {
        $ids = [];

        foreach (DatabaseSeeder::PRODUCTS[$productId][3] as $group) {
            $picked = match ($group) {
                1 => [$this->weighted([1 => 75, 2 => 25])],                 // Regular / Large
                2 => [$this->weighted([3 => 70, 4 => 22, 5 => 8])],         // gula
                3 => [$this->weighted([6 => 70, 7 => 20, 8 => 10])],        // es
                4 => [$this->weighted([0 => 75, 9 => 7, 10 => 12, 11 => 6])], // susu (0 = tidak memilih)
                5 => array_keys(array_filter([                               // tambahan, boleh lebih dari satu
                    12 => mt_rand(1, 100) <= 10,
                    13 => mt_rand(1, 100) <= 6,
                    14 => in_array($productId, [10, 23, 27], true) && mt_rand(1, 100) <= 15,
                ])),
                default => [],
            };

            array_push($ids, ...array_filter($picked));
        }

        return $ids;
    }

    protected function recordWaste(Carbon $day): void
    {
        for ($n = $this->weighted([0 => 30, 1 => 50, 2 => 20]); $n > 0; $n--) {
            [$name, $min, $max, $reason, $note] = self::WASTE[mt_rand(0, count(self::WASTE) - 1)];
            $this->at($day->copy()->setTime(mt_rand(10, 20), mt_rand(0, 59)));

            app(StockService::class)->recordWaste(
                Ingredient::where('name', $name)->firstOrFail(),
                mt_rand($min, $max),
                $reason,
                $note,
                $this->user()->id,
            );
        }
    }

    /**
     * @param  array<string, float>  $overrides  bahan => hitungan fisik
     */
    protected function completedOpname(Carbon $time, string $note, array $overrides): void
    {
        $this->at($time);
        $owner = $this->user('admin@lokal.com');
        $opname = app(OpnameService::class)->start($time->toDateString(), $note, $owner->id);
        $counts = [];

        foreach ($opname->items()->with('ingredient')->get() as $item) {
            $counts[$item->id] = $overrides[$item->ingredient->name] ?? $this->physicalCount($item->ingredient, (float) $item->system_qty);
        }

        app(OpnameService::class)->saveCounts($opname, $counts);
        $this->at($time->copy()->addMinutes(25));
        app(OpnameService::class)->complete($opname, $owner->id);
    }

    /**
     * Hitungan fisik: sebagian besar sama dengan sistem, sebagian sedikit kurang (susut).
     */
    protected function physicalCount(Ingredient $ingredient, float $system): float
    {
        if ($system <= 0) {
            return 0;
        }

        if (mt_rand(1, 100) <= 65) {
            return $system;
        }

        $counted = $system * (1 - mt_rand(5, 40) / 1000);

        return $ingredient->base_unit->value === 'pcs' ? floor($counted) : round($counted);
    }

    protected function draftOpname(): void
    {
        $this->at($this->clampToNow($this->today->copy()->setTime(15, 0)));
        $opname = app(OpnameService::class)->start($this->today->toDateString(), 'Opname bar (dicicil, belum selesai)', $this->user('kasir@lokal.com')->id);

        $counts = [];
        foreach ($opname->items()->with('ingredient')->get() as $item) {
            if (in_array($item->ingredient->ingredient_category_id, $this->categoryIds(['Kopi', 'Susu & Dairy']), true)) {
                $counts[$item->id] = $this->physicalCount($item->ingredient, (float) $item->system_qty);
            }
        }

        app(OpnameService::class)->saveCounts($opname, $counts);
    }

    /**
     * Pesanan hari ini yang memastikan skenario dashboard: whipped cream negatif, boba di bawah minimum.
     */
    protected function forcedOrders(): void
    {
        $time = $this->clampToNow($this->realNow->copy()->subMinutes(20));
        $this->placeOrder($time, [
            ['product_id' => 3, 'quantity' => 2, 'modifier_ids' => [1, 3, 6, 13]],
            ['product_id' => 26, 'quantity' => 1, 'modifier_ids' => [2, 3, 6, 13]],
            ['product_id' => 27, 'quantity' => 1, 'modifier_ids' => [1, 4, 7]],
        ], withInbox: true);
    }

    protected function catalogEvent(Carbon $time): void
    {
        $this->at($time);
        WebhookInbox::create([
            'event_id' => 'catalog.updated:' . $time->format('YmdHis'),
            'event_type' => 'catalog.updated',
            'payload' => ['event_type' => 'catalog.updated', 'timestamp' => $time->toIso8601String(), 'data' => ['product_ids' => [30, 31]]],
            'received_at' => $time,
            'processed_at' => $time->copy()->addSeconds(2),
            'attempts' => 1,
        ]);
    }

    /**
     * Void yang tidak bisa diproses: sale-nya belum tercatat dan POS tidak bisa dihubungi untuk mengambil detailnya.
     */
    protected function failedWebhook(): void
    {
        $time = $this->clampToNow($this->realNow->copy()->subHours(2));
        $uuid = (string) Str::uuid();
        $this->at($time);

        WebhookInbox::create([
            'event_id' => "order.voided:{$uuid}",
            'event_type' => 'order.voided',
            'payload' => ['event_type' => 'order.voided', 'idempotency_key' => "order.voided:{$uuid}", 'timestamp' => $time->toIso8601String(), 'data' => ['order_id' => $uuid, 'code' => '087', 'status' => 'voided', 'items' => []]],
            'received_at' => $time,
            'attempts' => 5,
            'last_error' => 'Gagal mengambil pesanan dari POS: cURL error 7: Failed to connect to 127.0.0.1 port 8000.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function inbox(string $type, string $uuid, array $items, string $status): void
    {
        $total = 0;
        $payloadItems = [];

        foreach ($items as $item) {
            $modifiers = PosModifier::whereIn('pos_modifier_id', $item['modifier_ids'])->get()
                ->map(fn (PosModifier $m) => ['modifier_id' => $m->pos_modifier_id, 'name' => $m->name, 'price_delta' => (int) $m->price_delta])
                ->all();
            $unit = $item['price'] + array_sum(array_column($modifiers, 'price_delta'));
            $total += $unit * $item['quantity'];
            $payloadItems[] = [
                'item_id' => $item['item_id'],
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unit,
                'modifiers' => $modifiers,
            ];
        }

        WebhookInbox::create([
            'event_id' => "{$type}:{$uuid}",
            'event_type' => $type,
            'payload' => [
                'event_type' => $type,
                'idempotency_key' => "{$type}:{$uuid}",
                'timestamp' => now()->toIso8601String(),
                'outlet_id' => 1,
                'data' => ['order_id' => $uuid, 'code' => sprintf('%03d', $this->orders), 'status' => $status, 'items' => $payloadItems, 'total' => $total],
            ],
            'received_at' => now(),
            'processed_at' => now()->addSeconds(mt_rand(1, 4)),
            'attempts' => 1,
        ]);
    }

    /**
     * @param  array<string>  $names
     * @return array<int>
     */
    protected function categoryIds(array $names): array
    {
        return IngredientCategory::whereIn('name', $names)->pluck('id')->all();
    }

    protected function user(?string $email = null): User
    {
        if ($email) {
            return $this->users[$email];
        }

        // Stok masuk dan waste dicatat bergantian oleh kasir, sesekali oleh pemilik.
        return $this->users[$this->weighted(['kasir@lokal.com' => 5, 'kasir2@lokal.com' => 4, 'admin@lokal.com' => 1])];
    }

    protected function at(Carbon $time): void
    {
        Carbon::setTestNow($time);
    }

    protected function clampToNow(Carbon $time): Carbon
    {
        return $time->greaterThan($this->realNow) ? $this->realNow->copy()->subMinute() : $time;
    }

    /**
     * @template T of int|string
     *
     * @param  array<T, int>  $weights
     * @return T
     */
    protected function weighted(array $weights): int|string
    {
        $pick = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if (($pick -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
