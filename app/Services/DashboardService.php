<?php

namespace App\Services;

use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\Ingredient;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka dashboard dari ledger stock_movements outlet aktif. Agregasi waktu dilakukan di PHP
 * supaya portabel MySQL & SQLite. Nilai rupiah memakai unit_cost untuk stok masuk dan avg_cost
 * bahan saat ini untuk pergerakan lain (ledger tidak menyimpan biaya per pergerakan keluar).
 * Tanpa $showCosts tidak ada angka rupiah yang dikembalikan, juga tidak di JSON halaman.
 */
class DashboardService
{
    public const RANGES = [7, 14, 30];

    /** Rata-rata pemakaian untuk perkiraan stok habis dihitung dari hari penuh terakhir. */
    protected const COVER_DAYS = 7;

    /** @var Collection<int, Ingredient> */
    protected Collection $ingredients;

    public function __construct(protected CostingService $costing)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $days, bool $showCosts): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $start = $today->copy()->subDays($days - 1);
        $from = $start->min($today->copy()->subDays(self::COVER_DAYS));

        $this->ingredients = Ingredient::query()->get()->keyBy('id');
        $movements = StockMovement::query()
            ->where('created_at', '>=', $from)
            ->get(['id', 'ingredient_id', 'type', 'qty', 'unit_cost', 'pos_order_uuid', 'reason', 'created_at']);

        $period = $movements->filter(fn (StockMovement $m) => $m->created_at->gte($start));
        $todayRows = $movements->filter(fn (StockMovement $m) => $m->created_at->gte($today));
        // Kemarin sampai jam yang sama, supaya perbandingan adil di tengah hari.
        $yesterdayRows = $movements->filter(fn (StockMovement $m) => $m->created_at->gte($today->copy()->subDay())
            && $m->created_at->lt($now->copy()->subDay()));

        $data = [
            'days' => $days,
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'show_costs' => $showCosts,
            'today' => $this->summary($todayRows),
            'yesterday' => $this->summary($yesterdayRows),
            'totals' => $this->summary($period),
            'trend' => $this->trend($period, $start, $days),
            'flow' => $this->flow($period),
            'waste' => $this->wasteByReason($period),
            'cover' => $this->cover($movements, $today),
        ];

        return $showCosts ? $data : $this->withoutCosts($data);
    }

    /**
     * Ringkasan sekelompok pergerakan.
     *
     * @param  Collection<int, StockMovement>  $rows
     * @return array{orders: int, usage_cost: int, waste_cost: int, waste_count: int, purchase_cost: int, purchase_count: int}
     */
    protected function summary(Collection $rows): array
    {
        $sales = $rows->filter(fn (StockMovement $m) => $m->type === StockMovementType::Sale);
        $voided = $rows->filter(fn (StockMovement $m) => $m->type === StockMovementType::VoidReturn)->pluck('pos_order_uuid')->unique();
        $waste = $rows->filter(fn (StockMovement $m) => $m->type === StockMovementType::Waste);
        $purchases = $rows->filter(fn (StockMovement $m) => $m->type === StockMovementType::Purchase);

        return [
            'orders' => $sales->pluck('pos_order_uuid')->unique()->diff($voided)->count(),
            'usage_cost' => $this->usageCost($rows),
            'waste_cost' => (int) round($waste->sum(fn (StockMovement $m) => $this->value($m))),
            'waste_count' => $waste->count(),
            'purchase_cost' => (int) round($purchases->sum(fn (StockMovement $m) => $this->value($m))),
            'purchase_count' => $purchases->count(),
        ];
    }

    /**
     * Biaya bahan terpakai untuk penjualan (sale dikurangi void_return).
     *
     * @param  Collection<int, StockMovement>  $rows
     */
    protected function usageCost(Collection $rows): int
    {
        return (int) round($rows
            ->filter(fn (StockMovement $m) => in_array($m->type, [StockMovementType::Sale, StockMovementType::VoidReturn], true))
            ->sum(fn (StockMovement $m) => -(float) $m->qty * $this->avgCost($m->ingredient_id)));
    }

    /**
     * @param  Collection<int, StockMovement>  $rows
     * @return array<int, array{date: string, orders: int, usage_cost: int, waste_cost: int}>
     */
    protected function trend(Collection $rows, Carbon $start, int $days): array
    {
        $byDay = $rows->groupBy(fn (StockMovement $m) => $m->created_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $byDay) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $summary = $this->summary($byDay->get($date, collect()));

            return ['date' => $date, 'orders' => $summary['orders'], 'usage_cost' => $summary['usage_cost'], 'waste_cost' => $summary['waste_cost']];
        })->all();
    }

    /**
     * Arus stok periode per jenis pergerakan. Selisih opname bertanda (+ ditemukan, − hilang).
     *
     * @param  Collection<int, StockMovement>  $rows
     * @return array<int, array{key: string, label: string, value: int, count: int}>
     */
    protected function flow(Collection $rows): array
    {
        $of = fn (StockMovementType ...$types) => $rows->filter(fn (StockMovement $m) => in_array($m->type, $types, true));
        $adjustments = $of(StockMovementType::Adjustment);

        return [
            ['key' => 'purchase', 'label' => 'Stok masuk', 'value' => (int) round($of(StockMovementType::Purchase)->sum(fn ($m) => $this->value($m))), 'count' => $of(StockMovementType::Purchase)->count()],
            ['key' => 'usage', 'label' => 'Terpakai untuk penjualan', 'value' => $this->usageCost($rows), 'count' => $of(StockMovementType::Sale)->count()],
            ['key' => 'waste', 'label' => 'Terbuang (waste)', 'value' => (int) round($of(StockMovementType::Waste)->sum(fn ($m) => $this->value($m))), 'count' => $of(StockMovementType::Waste)->count()],
            ['key' => 'adjustment', 'label' => 'Selisih opname', 'value' => (int) round($adjustments->sum(fn ($m) => (float) $m->qty * $this->avgCost($m->ingredient_id))), 'count' => $adjustments->count()],
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $rows
     * @return array<int, array{reason: string, label: string, value: int, count: int}>
     */
    protected function wasteByReason(Collection $rows): array
    {
        return $rows->filter(fn (StockMovement $m) => $m->type === StockMovementType::Waste)
            ->groupBy('reason')
            ->map(fn (Collection $group, string $reason) => [
                'reason' => $reason,
                'label' => WasteReason::tryFrom($reason)?->label() ?? $reason,
                'value' => (int) round($group->sum(fn ($m) => $this->value($m))),
                'count' => $group->count(),
            ])
            ->sortByDesc(fn (array $row) => [$row['value'], $row['count']])
            ->values()
            ->all();
    }

    /**
     * Perkiraan berapa hari stok cukup, dari rata-rata pemakaian (penjualan + waste) hari penuh terakhir.
     *
     * @param  Collection<int, StockMovement>  $rows
     * @return array<int, array{id: int, name: string, unit: string, stock: float, daily: float, days: float, min: float}>
     */
    protected function cover(Collection $rows, Carbon $today): array
    {
        $since = $today->copy()->subDays(self::COVER_DAYS);
        $used = $rows
            ->filter(fn (StockMovement $m) => $m->created_at->gte($since) && $m->created_at->lt($today)
                && in_array($m->type, [StockMovementType::Sale, StockMovementType::VoidReturn, StockMovementType::Waste], true))
            ->groupBy('ingredient_id')
            ->map(fn (Collection $group) => -$group->sum(fn (StockMovement $m) => (float) $m->qty) / self::COVER_DAYS);

        return $this->ingredients
            ->filter(fn (Ingredient $i) => $i->is_active && ($used[$i->id] ?? 0) > 0)
            ->map(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->base_unit->value,
                'stock' => (float) $i->current_stock,
                'min' => (float) $i->min_stock,
                'daily' => round($used[$i->id], 1),
                'days' => round(max(0, (float) $i->current_stock) / $used[$i->id], 1),
            ])
            ->sortBy('days')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Menu dengan margin terendah (HPP dari avg_cost terbaru). Hanya untuk pemilik.
     *
     * @return Collection<int, array{product: PosProduct, hpp: int, margin: int, margin_pct: float|null}>
     */
    public function lowestMargins(int $limit = 6): Collection
    {
        $withRecipe = RecipeItem::query()->distinct()->pluck('pos_product_id');

        return PosProduct::whereIn('pos_product_id', $withRecipe)->where('price', '>', 0)->get()
            ->map(fn (PosProduct $product) => ['product' => $product] + $this->costing->summary($product))
            ->sortBy('margin_pct')
            ->take($limit)
            ->values();
    }

    /**
     * Pergerakan manual terbaru (stok masuk, waste, opname, void), tanpa penjualan otomatis.
     *
     * @return Collection<int, StockMovement>
     */
    public function recentActivity(int $limit = 8): Collection
    {
        return StockMovement::with(['ingredient', 'creator'])
            ->where('type', '!=', StockMovementType::Sale->value)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Opname yang belum selesai: [opname, jumlah dihitung, jumlah baris].
     *
     * @return array{0: StockOpname, 1: int, 2: int}|null
     */
    public function draftOpname(): ?array
    {
        $opname = StockOpname::where('status', OpnameStatus::Draft->value)->latest('opname_date')->latest('id')->first();

        if (! $opname) {
            return null;
        }

        return [$opname, $opname->items()->whereNotNull('counted_qty')->count(), $opname->items()->count()];
    }

    protected function value(StockMovement $m): float
    {
        $cost = $m->type === StockMovementType::Purchase && $m->unit_cost !== null
            ? (float) $m->unit_cost
            : $this->avgCost($m->ingredient_id);

        return abs((float) $m->qty) * $cost;
    }

    protected function avgCost(int $ingredientId): float
    {
        return (float) ($this->ingredients[$ingredientId]->avg_cost ?? 0);
    }

    /**
     * Buang semua angka rupiah untuk pengguna tanpa hak melihat biaya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withoutCosts(array $data): array
    {
        $strip = fn (array $row) => array_diff_key($row, array_flip(['usage_cost', 'waste_cost', 'purchase_cost', 'value']));

        foreach (['today', 'yesterday', 'totals'] as $key) {
            $data[$key] = $strip($data[$key]);
        }

        foreach (['trend', 'flow', 'waste'] as $key) {
            $data[$key] = array_map($strip, $data[$key]);
        }

        return $data;
    }
}
