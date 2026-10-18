<?php

namespace App\Services;

use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpnameService
{
    public function __construct(
        protected StockService $stock,
        protected MenuAvailabilityService $availability,
    ) {
    }

    /**
     * Buat opname baru dengan snapshot stok sistem semua bahan aktif.
     */
    public function start(string $date, ?string $note = null, ?int $userId = null): StockOpname
    {
        return DB::transaction(function () use ($date, $note, $userId) {
            $opname = StockOpname::create([
                'opname_date' => $date,
                'status' => OpnameStatus::Draft,
                'note' => $note,
                'created_by' => $userId,
            ]);

            Ingredient::where('is_active', true)->orderBy('name')->get()->each(
                fn (Ingredient $ingredient) => $opname->items()->create([
                    'ingredient_id' => $ingredient->id,
                    'system_qty' => $ingredient->current_stock,
                ])
            );

            return $opname;
        });
    }

    /**
     * Simpan hitungan fisik. Kosong (null) berarti belum dihitung. Opname selesai tidak bisa diubah.
     *
     * @param  array<int, float|string|null>  $counts  opname_item_id => hitungan fisik
     */
    public function saveCounts(StockOpname $opname, array $counts): void
    {
        DB::transaction(function () use ($opname, $counts) {
            // Kunci baris opname: kiriman bersamaan dengan "selesaikan" menunggu, lalu ditolak bila sudah selesai.
            $locked = StockOpname::lockForUpdate()->findOrFail($opname->id);
            $this->assertDraft($locked);

            foreach ($locked->items as $item) {
                if (! array_key_exists($item->id, $counts)) {
                    continue;
                }

                $value = $counts[$item->id];
                $counted = ($value === null || $value === '') ? null : round((float) $value, 3);

                $item->update([
                    'counted_qty' => $counted,
                    'difference' => $counted === null ? null : round($counted - (float) $item->system_qty, 3),
                ]);
            }
        });
    }

    /**
     * Selesaikan opname: tiap selisih ≠ 0 menjadi satu pergerakan adjustment.
     * Selisih dihitung dari snapshot saat dibuat, dan diterapkan ke stok saat ini.
     * Baris opname dikunci di dalam transaksi, jadi request ganda yang bersamaan hanya berhasil sekali.
     */
    public function complete(StockOpname $opname, ?int $userId = null): StockOpname
    {
        $touched = [];

        DB::transaction(function () use ($opname, $userId, &$touched) {
            $locked = StockOpname::lockForUpdate()->findOrFail($opname->id);
            $this->assertDraft($locked);

            foreach ($locked->items()->whereNotNull('counted_qty')->get() as $item) {
                $difference = round((float) $item->counted_qty - (float) $item->system_qty, 3);

                if ($difference == 0.0) {
                    continue;
                }

                $this->stock->record(
                    $item->ingredient_id,
                    StockMovementType::Adjustment,
                    $difference,
                    'opname_item',
                    (string) $item->id,
                    ['created_by' => $userId, 'note' => "Opname #{$locked->id}"],
                );

                $touched[] = $item->ingredient_id;
            }

            $locked->update([
                'status' => OpnameStatus::Completed,
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);
        });

        $this->availability->refreshForIngredients($touched);

        return $opname->refresh();
    }

    protected function assertDraft(StockOpname $opname): void
    {
        if ($opname->isCompleted()) {
            throw new RuntimeException('Opname yang sudah selesai tidak bisa diubah.');
        }
    }
}
