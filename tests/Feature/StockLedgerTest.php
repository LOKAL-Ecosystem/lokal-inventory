<?php

use App\Enums\BaseUnit;
use App\Enums\OpnameStatus;
use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Jobs\PushAvailabilityToPos;
use App\Models\Ingredient;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Services\CostingService;
use App\Services\MenuAvailabilityService;
use App\Services\OpnameService;
use App\Services\StockDeductionService;
use App\Services\StockReceiptService;
use App\Services\StockService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->outlet = F::outlet();
    F::as($this->outlet);
});

function receive(Ingredient $ingredient, float $purchaseQty, int $totalPrice): void
{
    app(StockReceiptService::class)->create(
        ['received_at' => now()->toDateString()],
        [['ingredient_id' => $ingredient->id, 'purchase_qty' => $purchaseQty, 'total_price' => $totalPrice]],
    );
}

// --- Stok masuk & avg_cost ---------------------------------------------------------------

it('mengonversi satuan beli ke satuan dasar dan mengisi avg_cost', function () {
    $milk = Ingredient::create(['name' => 'Susu', 'base_unit' => BaseUnit::Milliliter, 'purchase_unit' => 'karton', 'purchase_unit_qty' => 12000]);

    receive($milk, 2, 480000); // 24.000 ml, Rp 20 / ml

    $milk->refresh();
    expect((float) $milk->current_stock)->toBe(24000.0)->and((float) $milk->avg_cost)->toBe(20.0);
});

it('menghitung avg_cost rata-rata tertimbang', function () {
    $beans = F::ingredient('Biji Kopi', 0); // purchase_unit_qty 1000 g
    receive($beans, 1, 100000);             // 1000 g @ Rp 100
    receive($beans, 1, 200000);             // 1000 g @ Rp 200 -> rata-rata 150

    expect((float) $beans->fresh()->avg_cost)->toBe(150.0)->and((float) $beans->fresh()->current_stock)->toBe(2000.0);
});

it('memakai harga beli sebagai avg_cost bila stok lama ≤ 0', function () {
    $beans = F::ingredient('Biji Kopi', 0, cost: 999);
    app(StockService::class)->record($beans, StockMovementType::Adjustment, -50, 'test', 'neg'); // stok -50

    receive($beans, 1, 120000); // 1000 g @ Rp 120

    expect((float) $beans->fresh()->avg_cost)->toBe(120.0)->and((float) $beans->fresh()->current_stock)->toBe(950.0);

    // stok tepat 0 juga memakai harga beli
    $sugar = F::ingredient('Gula', 0, cost: 777);
    receive($sugar, 1, 50000);
    expect((float) $sugar->fresh()->avg_cost)->toBe(50.0);
});

it('menyimpan avg_cost pecahan per satuan dasar tanpa pembulatan prematur', function () {
    $milk = Ingredient::create(['name' => 'Susu', 'base_unit' => BaseUnit::Milliliter, 'purchase_unit' => 'karton', 'purchase_unit_qty' => 12000]);
    receive($milk, 1, 300); // Rp 0,025 / ml

    expect((float) $milk->fresh()->avg_cost)->toBe(0.025);
});

// --- Ledger --------------------------------------------------------------------------------

it('ledger append-only: tidak bisa diubah atau dihapus', function () {
    $item = F::ingredient('Kopi', 100);
    $movement = StockMovement::first();

    expect(fn () => $movement->update(['qty' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class)
        ->and((float) $item->fresh()->current_stock)->toBe(100.0);
});

it('constraint unik database menolak pergerakan duplikat', function () {
    $item = F::ingredient('Kopi');
    $attributes = ['outlet_id' => $this->outlet->id, 'ingredient_id' => $item->id, 'type' => 'sale', 'qty' => -1, 'source_type' => 'pos_order_item', 'source_id' => 'x#0'];

    StockMovement::create($attributes);

    expect(fn () => StockMovement::create($attributes))->toThrow(\Illuminate\Database\QueryException::class);
});

it('inventory:rebuild-stock memperbaiki cache dari ledger', function () {
    $item = F::ingredient('Kopi', 100);
    $item->forceFill(['current_stock' => 7])->save();

    $this->artisan('inventory:rebuild-stock')->assertSuccessful();

    expect((float) $item->fresh()->current_stock)->toBe(100.0);
});

it('stok boleh negatif', function () {
    $item = F::ingredient('Kopi', 10);
    F::product(1);
    F::recipe(1, [$item->id => 18]);

    app(StockDeductionService::class)->processConfirmed(['order_id' => 'neg-1', 'items' => [F::line(1, 1)]]);

    expect((float) $item->fresh()->current_stock)->toBe(-8.0)->and($item->fresh()->isNegative())->toBeTrue();
});

// --- Waste -----------------------------------------------------------------------------------

it('mencatat waste dalam satuan dasar atau satuan beli', function () {
    $item = F::ingredient('Susu', 5000, BaseUnit::Milliliter);

    app(StockService::class)->recordWaste($item, 200, WasteReason::Spilled, 'tumpah di bar');
    app(StockService::class)->recordWaste($item, 1, WasteReason::Expired, null, null, true); // 1 pack = 1000 ml

    $item->refresh();
    expect((float) $item->current_stock)->toBe(3800.0);
    $waste = StockMovement::where('type', 'waste')->orderBy('id')->get();
    expect($waste)->toHaveCount(2)->and($waste[0]->reason)->toBe('spilled')->and((float) $waste[1]->qty)->toBe(-1000.0);
});

// --- Opname ----------------------------------------------------------------------------------

it('opname: selisih menjadi satu adjustment dan opname selesai terkunci', function () {
    $a = F::ingredient('Kopi', 1000);
    $b = F::ingredient('Susu', 500, BaseUnit::Milliliter);
    $c = F::ingredient('Gula', 300);
    $service = app(OpnameService::class);

    $opname = $service->start(now()->toDateString());
    expect($opname->items)->toHaveCount(3);

    $items = $opname->items->keyBy('ingredient_id');
    $service->saveCounts($opname, [
        $items[$a->id]->id => 950,   // -50
        $items[$b->id]->id => 500,   // sama -> tanpa adjustment
        $items[$c->id]->id => 320.5, // +20.5
    ]);

    $service->complete($opname, null);

    expect(StockMovement::where('type', 'adjustment')->where('source_type', 'opname_item')->count())->toBe(2)
        ->and((float) $a->fresh()->current_stock)->toBe(950.0)
        ->and((float) $b->fresh()->current_stock)->toBe(500.0)
        ->and((float) $c->fresh()->current_stock)->toBe(320.5)
        ->and($opname->fresh()->status)->toBe(OpnameStatus::Completed);

    expect(fn () => $service->saveCounts($opname->fresh(), [$items[$a->id]->id => 1]))->toThrow(RuntimeException::class)
        ->and(fn () => $service->complete($opname->fresh()))->toThrow(RuntimeException::class);
});

it('opname: bahan yang belum dihitung tidak diubah', function () {
    $a = F::ingredient('Kopi', 1000);
    $service = app(OpnameService::class);
    $opname = $service->start(now()->toDateString());

    $service->complete($opname);

    expect((float) $a->fresh()->current_stock)->toBe(1000.0)
        ->and(StockMovement::where('source_type', 'opname_item')->count())->toBe(0);
});

// --- Ketersediaan menu -------------------------------------------------------------------------

it('menandai menu habis hanya saat status berubah, dan tersedia lagi setelah stok masuk', function () {
    Queue::fake();
    $beans = F::ingredient('Kopi', 40);
    F::product(1, 'Americano');
    F::recipe(1, [$beans->id => 18]);
    $deduct = app(StockDeductionService::class);

    $deduct->processConfirmed(['order_id' => 'a1', 'items' => [F::line(1, 1)]]); // 22 -> masih cukup
    Queue::assertNothingPushed();

    $deduct->processConfirmed(['order_id' => 'a2', 'items' => [F::line(1, 1)]]); // 4 -> habis
    Queue::assertPushed(PushAvailabilityToPos::class, 1);
    expect(PosProduct::first())->auto_unavailable->toBeTrue()->is_available->toBeFalse();

    $deduct->processConfirmed(['order_id' => 'a3', 'items' => [F::line(1, 1)]]); // tetap habis -> tidak kirim lagi
    Queue::assertPushed(PushAvailabilityToPos::class, 1);

    receive($beans, 1, 100000); // stok pulih -> tersedia lagi
    Queue::assertPushed(PushAvailabilityToPos::class, 2);
    Queue::assertPushed(PushAvailabilityToPos::class, fn ($job) => $job->available === true);
    expect(PosProduct::first())->auto_unavailable->toBeFalse()->is_available->toBeTrue();
});

it('tidak menimpa tanda habis manual dari POS', function () {
    Queue::fake();
    $beans = F::ingredient('Kopi', 100);
    $product = F::product(1, 'Americano');
    $product->update(['is_available' => false]); // ditandai habis manual di POS
    F::recipe(1, [$beans->id => 18]);

    app(MenuAvailabilityService::class)->refreshAll();
    Queue::assertNothingPushed();
    expect($product->fresh())->auto_unavailable->toBeFalse()->is_available->toBeFalse();

    // Stok cukup & tidak ada tanda otomatis: tetap habis (manual).
    receive($beans, 1, 1000);
    expect($product->fresh()->is_available)->toBeFalse();
});

it('menghitung porsi tersedia dari bahan yang paling sedikit', function () {
    $beans = F::ingredient('Kopi', 100);
    $milk = F::ingredient('Susu', 1000, BaseUnit::Milliliter);
    F::product(1);
    F::recipe(1, [$beans->id => 18, $milk->id => 150]);

    expect(app(MenuAvailabilityService::class)->portions(1))->toBe(5);
});

// --- HPP & margin ------------------------------------------------------------------------------

it('menghitung HPP dan margin dari avg_cost', function () {
    $beans = F::ingredient('Kopi', 1000, cost: 100); // Rp 100 / g
    $milk = F::ingredient('Susu', 5000, BaseUnit::Milliliter, cost: 20);
    $product = F::product(1, 'Latte', 25000);
    F::recipe(1, [$beans->id => 18, $milk->id => 150]); // 1800 + 3000

    $summary = app(CostingService::class)->summary($product);

    expect($summary)->toMatchArray(['hpp' => 4800, 'margin' => 20200, 'margin_pct' => 80.8, 'has_recipe' => true]);
    expect(app(CostingService::class)->stockValue())->toBe(1000 * 100 + 5000 * 20);
});

it('HPP ikut berubah saat stok masuk mengubah avg_cost', function () {
    $beans = F::ingredient('Kopi', 1000, cost: 100);
    $product = F::product(1, 'Americano', 20000);
    F::recipe(1, [$beans->id => 10]);

    expect(app(CostingService::class)->summary($product)['hpp'])->toBe(1000);

    receive($beans, 1, 200000); // +1000 g @ 200 -> avg 150

    expect(app(CostingService::class)->summary($product)['hpp'])->toBe(1500);
});

// --- Isolasi outlet ----------------------------------------------------------------------------

it('satu outlet tidak bisa melihat data outlet lain', function () {
    $mine = F::ingredient('Kopi A', 100);
    $other = F::outlet('Outlet B', 'whsec_b');

    F::as($other);
    $theirs = F::ingredient('Kopi B', 50);
    expect(Ingredient::count())->toBe(1)
        ->and(Ingredient::find($mine->id))->toBeNull()
        ->and(StockMovement::count())->toBe(1);

    F::as($this->outlet);
    expect(Ingredient::pluck('name')->all())->toBe(['Kopi A'])
        ->and(Ingredient::find($theirs->id))->toBeNull();
});

it('tanpa outlet aktif, query data usaha tidak mengembalikan apa pun dan tidak bisa menulis', function () {
    F::ingredient('Kopi', 100);
    app(\App\Support\CurrentOutlet::class)->set(null);

    expect(Ingredient::count())->toBe(0)
        ->and(fn () => Ingredient::create(['name' => 'X', 'base_unit' => BaseUnit::Gram]))->toThrow(LogicException::class);
});

it('webhook outlet A tidak memotong stok bahan outlet B yang punya produk sama', function () {
    $beansA = F::ingredient('Kopi', 1000);
    F::product(12);
    F::recipe(12, [$beansA->id => 18]);

    $b = F::outlet('B', 'whsec_b');
    F::as($b);
    $beansB = F::ingredient('Kopi', 1000);
    F::product(12);
    F::recipe(12, [$beansB->id => 18]);

    F::as($this->outlet);
    app(StockDeductionService::class)->processConfirmed(['order_id' => 'iso-1', 'items' => [F::line(12, 1)]]);

    F::as($b);
    expect((float) $beansB->fresh()->current_stock)->toBe(1000.0);
    F::as($this->outlet);
    expect((float) $beansA->fresh()->current_stock)->toBe(982.0);
});
