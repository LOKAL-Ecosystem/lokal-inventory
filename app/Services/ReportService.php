<?php

namespace App\Services;

use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan sederhana, hasilnya seragam: judul, kolom, baris (array of array) supaya mudah
 * ditampilkan, diekspor CSV, dan dicetak PDF. Agregasi dilakukan di PHP agar portabel (MySQL/SQLite).
 */
class ReportService
{
    public const TYPES = [
        'movements' => 'Pergerakan Stok',
        'usage' => 'Pemakaian Bahan',
        'stock_value' => 'Nilai Stok',
        'hpp' => 'HPP & Margin per Menu',
        'opname' => 'Selisih Opname',
        'no_recipe' => 'Menu Tanpa Resep',
    ];

    public function __construct(protected CostingService $costing)
    {
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float>>}
     */
    public function build(string $type, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return match ($type) {
            'usage' => $this->usage($from, $to),
            'stock_value' => $this->stockValue(),
            'hpp' => $this->hpp(),
            'opname' => $this->opname($from, $to),
            'no_recipe' => $this->noRecipe(),
            default => $this->movements($from, $to),
        };
    }

    protected function movements(Carbon $from, Carbon $to): array
    {
        $rows = StockMovement::with(['ingredient', 'creator'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->map(fn (StockMovement $m) => [
                $m->created_at->format('Y-m-d H:i'),
                $m->ingredient->name,
                $m->type->label(),
                (float) $m->qty,
                $m->ingredient->base_unit->value,
                $m->reasonLabel() ?? ($m->note ?? ''),
                $m->creator->name ?? ($m->pos_order_uuid ? 'POS' : '-'),
            ])->all();

        return [
            'title' => self::TYPES['movements'],
            'columns' => ['Waktu', 'Bahan', 'Jenis', 'Qty', 'Satuan', 'Keterangan', 'Oleh'],
            'rows' => $rows,
        ];
    }

    protected function usage(Carbon $from, Carbon $to): array
    {
        $movements = StockMovement::with('ingredient')
            ->whereIn('type', [StockMovementType::Sale->value, StockMovementType::VoidReturn->value])
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->groupBy('ingredient_id');

        $rows = $movements->map(function (Collection $group) {
            $ingredient = $group->first()->ingredient;
            // Pemakaian bersih = penjualan dikurangi pengembalian void.
            $used = -1 * (float) $group->sum('qty');

            return [$ingredient->name, round($used, 3), $ingredient->base_unit->value, (int) round($used * (float) $ingredient->avg_cost)];
        })->sortBy(0)->values()->all();

        return [
            'title' => self::TYPES['usage'],
            'columns' => ['Bahan', 'Pemakaian', 'Satuan', 'Perkiraan Biaya (Rp)'],
            'rows' => $rows,
        ];
    }

    protected function stockValue(): array
    {
        $rows = Ingredient::where('is_active', true)->orderBy('name')->get()->map(fn (Ingredient $i) => [
            $i->name,
            (float) $i->current_stock,
            $i->base_unit->value,
            (float) $i->avg_cost,
            (int) round(max(0, (float) $i->current_stock) * (float) $i->avg_cost),
        ])->all();

        return [
            'title' => self::TYPES['stock_value'],
            'columns' => ['Bahan', 'Stok', 'Satuan', 'Rata-rata Biaya / Satuan (Rp)', 'Nilai (Rp)'],
            'rows' => $rows,
        ];
    }

    protected function hpp(): array
    {
        $rows = PosProduct::orderBy('name')->get()->map(function (PosProduct $p) {
            $s = $this->costing->summary($p);

            return $s['has_recipe']
                ? [$p->name, $p->price, $s['hpp'], $s['margin'], $s['margin_pct'] ?? '-']
                : [$p->name, $p->price, '-', '-', '-'];
        })->all();

        return [
            'title' => self::TYPES['hpp'],
            'columns' => ['Menu', 'Harga Jual (Rp)', 'HPP (Rp)', 'Margin (Rp)', 'Margin (%)'],
            'rows' => $rows,
        ];
    }

    protected function opname(Carbon $from, Carbon $to): array
    {
        $rows = StockOpnameItem::with(['ingredient', 'opname'])
            ->whereHas('opname', fn ($q) => $q
                ->where('status', OpnameStatus::Completed->value)
                ->whereBetween('opname_date', [$from->toDateString(), $to->toDateString()]))
            ->whereNotNull('difference')
            ->where('difference', '!=', 0)
            ->get()
            ->sortBy(fn ($i) => $i->opname->opname_date->format('Y-m-d') . $i->ingredient->name)
            ->map(fn (StockOpnameItem $i) => [
                $i->opname->opname_date->format('Y-m-d'),
                $i->ingredient->name,
                (float) $i->system_qty,
                (float) $i->counted_qty,
                (float) $i->difference,
                $i->ingredient->base_unit->value,
            ])->values()->all();

        return [
            'title' => self::TYPES['opname'],
            'columns' => ['Tanggal', 'Bahan', 'Sistem', 'Fisik', 'Selisih', 'Satuan'],
            'rows' => $rows,
        ];
    }

    protected function noRecipe(): array
    {
        $rows = PosProduct::whereNotIn('pos_product_id', RecipeItem::query()->select('pos_product_id'))
            ->orderByDesc('missing_recipe_hits')->orderBy('name')->get()
            ->map(fn (PosProduct $p) => [
                $p->name,
                $p->category ?? '-',
                $p->missing_recipe_hits,
                $p->last_missing_recipe_at?->format('Y-m-d H:i') ?? '-',
            ])->all();

        return [
            'title' => self::TYPES['no_recipe'],
            'columns' => ['Menu', 'Kategori', 'Terjual Tanpa Resep (kali)', 'Terakhir'],
            'rows' => $rows,
        ];
    }
}
