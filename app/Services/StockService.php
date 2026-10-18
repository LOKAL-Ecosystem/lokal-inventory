<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu penulisan ledger stok. Menjaga cache current_stock, avg_cost,
 * dan idempotensi tetap konsisten. Jangan memanggil StockMovement::create() dari tempat lain.
 */
class StockService
{
    public function __construct(protected MenuAvailabilityService $availability)
    {
    }

    /**
     * Catat satu pergerakan. Mengembalikan null bila pergerakan yang sama sudah ada (idempoten).
     * Dipanggil di dalam transaksi pemanggil; cache stok ikut diperbarui di transaksi yang sama.
     *
     * @param  array{unit_cost?: float|null, pos_order_uuid?: string|null, reason?: string|null, note?: string|null, created_by?: int|null}  $extra
     */
    public function record(
        Ingredient|int $ingredient,
        StockMovementType $type,
        float $qty,
        string $sourceType,
        string $sourceId,
        array $extra = [],
    ): ?StockMovement {
        $ingredientId = $ingredient instanceof Ingredient ? $ingredient->id : $ingredient;
        $qty = round($qty, 3);

        return DB::transaction(function () use ($ingredientId, $type, $qty, $sourceType, $sourceId, $extra) {
            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredientId);

            $exists = StockMovement::query()
                ->where('type', $type->value)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('ingredient_id', $ingredientId)
                ->exists();

            if ($exists) {
                return null;
            }

            try {
                $movement = DB::transaction(fn () => StockMovement::create([
                    'ingredient_id' => $ingredientId,
                    'type' => $type,
                    'qty' => $qty,
                    'unit_cost' => $extra['unit_cost'] ?? null,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'pos_order_uuid' => $extra['pos_order_uuid'] ?? null,
                    'reason' => $extra['reason'] ?? null,
                    'note' => $extra['note'] ?? null,
                    'created_by' => $extra['created_by'] ?? null,
                ]));
            } catch (QueryException $e) {
                // Unique constraint (permintaan paralel yang sama): anggap duplikat.
                if (in_array($e->getCode(), ['23000', '23505'], true)) {
                    return null;
                }

                throw $e;
            }

            $stockBefore = (float) $locked->current_stock;

            if ($type === StockMovementType::Purchase && ($extra['unit_cost'] ?? null) !== null && $qty > 0) {
                $locked->avg_cost = $this->weightedAverageCost(
                    $stockBefore,
                    (float) $locked->avg_cost,
                    $qty,
                    (float) $extra['unit_cost'],
                );
            }

            $locked->current_stock = round($stockBefore + $qty, 3);
            $locked->save();

            return $movement;
        });
    }

    /**
     * avg_cost baru = (stok lama × avg lama + qty beli × harga beli) / (stok lama + qty beli).
     * Bila stok lama ≤ 0, avg_cost = harga beli.
     */
    public function weightedAverageCost(float $oldStock, float $oldAvg, float $boughtQty, float $unitCost): float
    {
        if ($oldStock <= 0) {
            return round($unitCost, 4);
        }

        return round(($oldStock * $oldAvg + $boughtQty * $unitCost) / ($oldStock + $boughtQty), 4);
    }

    /**
     * Catat bahan terbuang. $inPurchaseUnit = true bila $qty dalam satuan beli.
     */
    public function recordWaste(
        Ingredient $ingredient,
        float $qty,
        WasteReason $reason,
        ?string $note = null,
        ?int $userId = null,
        bool $inPurchaseUnit = false,
    ): StockMovement {
        $baseQty = $inPurchaseUnit ? $qty * (float) $ingredient->purchase_unit_qty : $qty;

        $movement = $this->record(
            $ingredient,
            StockMovementType::Waste,
            -abs($baseQty),
            'waste',
            (string) Str::uuid(),
            ['reason' => $reason->value, 'note' => $note, 'created_by' => $userId],
        );

        $this->availability->refreshForIngredients([$ingredient->id]);

        return $movement;
    }

    /**
     * Hitung ulang current_stock dari ledger (untuk command inventory:rebuild-stock).
     */
    public function rebuild(Ingredient $ingredient): float
    {
        $sum = (float) StockMovement::where('ingredient_id', $ingredient->id)->sum('qty');
        $ingredient->forceFill(['current_stock' => round($sum, 3)])->save();

        return round($sum, 3);
    }
}
