<?php

use App\Enums\RecipeAdjustmentAction;
use App\Enums\StockMovementType;
use App\Models\ModifierRecipeAdjustment;
use App\Models\StockMovement;
use App\Services\StockDeductionService;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->outlet = F::outlet();
    F::as($this->outlet);

    $this->coffee = F::ingredient('Biji Kopi', 1000);
    $this->milk = F::ingredient('Susu Sapi', 5000, \App\Enums\BaseUnit::Milliliter);
    $this->oat = F::ingredient('Oat Milk', 3000, \App\Enums\BaseUnit::Milliliter);
    $this->cup = F::ingredient('Cup', 100, \App\Enums\BaseUnit::Piece);

    F::product(12, 'Cafe Latte');
    F::recipe(12, [$this->coffee->id => 18, $this->milk->id => 150, $this->cup->id => 1]);

    $this->service = app(StockDeductionService::class);
});

function orderPayload(string $uuid, array $lines): array
{
    return ['order_id' => $uuid, 'status' => 'confirmed', 'items' => $lines];
}

it('memotong stok sesuai resep dasar dikali qty', function () {
    $this->service->processConfirmed(orderPayload('order-1', [F::line(12, 2)]));

    expect((float) $this->coffee->fresh()->current_stock)->toBe(964.0)
        ->and((float) $this->milk->fresh()->current_stock)->toBe(4700.0)
        ->and((float) $this->cup->fresh()->current_stock)->toBe(98.0);

    $sale = StockMovement::where('type', StockMovementType::Sale->value)->where('ingredient_id', $this->coffee->id)->first();
    expect((float) $sale->qty)->toBe(-36.0)->and($sale->pos_order_uuid)->toBe('order-1');
});

it('menerapkan penyesuaian modifier: replace, add, lalu multiply', function () {
    // opsi 5: oat milk menggantikan susu sapi; opsi 9: extra shot (+18 g kopi); opsi 3: large (x1.5 semua bahan)
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 5, 'action' => RecipeAdjustmentAction::Replace, 'ingredient_id' => $this->oat->id, 'replaces_ingredient_id' => $this->milk->id]);
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 9, 'action' => RecipeAdjustmentAction::Add, 'ingredient_id' => $this->coffee->id, 'qty' => 18]);
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 3, 'action' => RecipeAdjustmentAction::Multiply, 'factor' => 1.5]);

    $this->service->processConfirmed(orderPayload('order-2', [F::line(12, 1, [5, 9, 3])]));

    // kopi (18 + 18) x1.5 = 54; susu sapi diganti oat 150 x1.5 = 225; cup 1 x1.5 = 1.5
    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000 - 54.0)
        ->and((float) $this->milk->fresh()->current_stock)->toBe(5000.0)
        ->and((float) $this->oat->fresh()->current_stock)->toBe(3000 - 225.0)
        ->and((float) $this->cup->fresh()->current_stock)->toBe(100 - 1.5);
});

it('multiply bisa menargetkan satu bahan saja', function () {
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 7, 'action' => RecipeAdjustmentAction::Multiply, 'ingredient_id' => $this->milk->id, 'factor' => 2]);

    $this->service->processConfirmed(orderPayload('order-3', [F::line(12, 1, [7])]));

    expect((float) $this->milk->fresh()->current_stock)->toBe(5000 - 300.0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000 - 18.0);
});

it('hanya memotong sekali walau pesanan diproses berulang', function () {
    $payload = orderPayload('order-4', [F::line(12, 1)]);

    $first = $this->service->processConfirmed($payload);
    $second = $this->service->processConfirmed($payload);

    expect($first)->toBe(3)->and($second)->toBe(0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(982.0);
});

it('tidak memotong lagi walau resep berubah di antara dua pemrosesan', function () {
    $payload = orderPayload('order-5', [F::line(12, 1)]);
    $this->service->processConfirmed($payload);

    F::recipe(12, [F::ingredient('Sirup', 500)->id => 10]);
    $this->service->processConfirmed($payload);

    expect((float) \App\Models\Ingredient::where('name', 'Sirup')->first()->current_stock)->toBe(500.0);
});

it('melewati produk tanpa resep dan mencatatnya untuk laporan', function () {
    F::product(99, 'Menu Baru');

    $created = $this->service->processConfirmed(orderPayload('order-6', [F::line(99, 3)]));

    expect($created)->toBe(0);
    $product = \App\Models\PosProduct::where('pos_product_id', 99)->first();
    expect($product->missing_recipe_hits)->toBe(1)->and($product->last_missing_recipe_at)->not->toBeNull();
});

it('membuat void_return dengan jumlah persis sama dari ledger', function () {
    $this->service->processConfirmed(orderPayload('order-7', [F::line(12, 2)]));

    // Resep berubah sesudah penjualan: void tetap mengembalikan jumlah lama.
    \App\Models\RecipeItem::where('ingredient_id', $this->coffee->id)->update(['qty' => 99]);

    $returned = $this->service->processVoided(['order_id' => 'order-7']);

    expect($returned)->toBe(3)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0)
        ->and((float) $this->milk->fresh()->current_stock)->toBe(5000.0);

    // Void kedua tidak mengembalikan lagi.
    expect($this->service->processVoided(['order_id' => 'order-7']))->toBe(0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('menunda void sampai sale tercatat: memproses confirmed dari inbox dulu', function () {
    \App\Models\WebhookInbox::create([
        'event_id' => 'order.confirmed:order-8',
        'event_type' => 'order.confirmed',
        'payload' => F::webhook('order.confirmed', 'order-8', [F::line(12, 1)]),
        'received_at' => now(),
    ]);

    $this->service->processVoided(['order_id' => 'order-8'], $this->outlet);

    // sale lalu void_return: bersih
    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0)
        ->and(StockMovement::where('pos_order_uuid', 'order-8')->count())->toBe(6)
        ->and(\App\Models\WebhookInbox::first()->processed_at)->not->toBeNull();
});

it('mengambil detail pesanan dari API bila void datang tanpa confirmed', function () {
    \Illuminate\Support\Facades\Http::fake([
        'pos.test/api/v1/orders/order-9' => \Illuminate\Support\Facades\Http::response([
            'success' => true,
            'data' => ['id' => 'order-9', 'status' => 'voided', 'items' => [F::line(12, 1)]],
        ]),
    ]);

    $this->service->processVoided(['order_id' => 'order-9'], $this->outlet);

    expect(StockMovement::where('pos_order_uuid', 'order-9')->where('type', 'sale')->count())->toBe(3)
        ->and(StockMovement::where('pos_order_uuid', 'order-9')->where('type', 'void_return')->count())->toBe(3)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('mencocokkan opsi lewat nama bila payload REST tanpa modifier_id', function () {
    $product = \App\Models\PosProduct::where('pos_product_id', 12)->first();
    \App\Models\PosModifier::create(['pos_modifier_id' => 5, 'pos_group_id' => 2, 'name' => 'Oat Milk']);
    \Illuminate\Support\Facades\DB::table('pos_product_modifiers')->insert(['outlet_id' => $this->outlet->id, 'pos_product_id' => 12, 'pos_modifier_id' => 5]);
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 5, 'action' => RecipeAdjustmentAction::Replace, 'ingredient_id' => $this->oat->id, 'replaces_ingredient_id' => $this->milk->id]);

    $this->service->processConfirmed([
        'id' => 'order-10',
        'items' => [['product_id' => 12, 'name' => 'Cafe Latte', 'quantity' => 1, 'modifiers' => [['name' => 'Oat Milk', 'price_delta' => 8000]]]],
    ]);

    expect((float) $this->oat->fresh()->current_stock)->toBe(3000 - 150.0)
        ->and((float) $this->milk->fresh()->current_stock)->toBe(5000.0);
});
