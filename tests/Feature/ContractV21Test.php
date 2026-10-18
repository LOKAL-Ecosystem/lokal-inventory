<?php

use App\Enums\RecipeAdjustmentAction;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Jobs\PushAvailabilityToPos;
use App\Jobs\PushStockAlerts;
use App\Models\ModifierRecipeAdjustment;
use App\Models\PosProduct;
use App\Models\StockMovement;
use App\Services\MenuAvailabilityService;
use App\Services\StockDeductionService;
use App\Services\StockService;
use App\Support\CurrentOutlet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->withoutVite();
    $this->outlet = F::outlet('Lokal Coffee', 'whsec_outlet_a');
    F::as($this->outlet);
    $this->coffee = F::ingredient('Biji Kopi', 1000);
    F::product(12, 'Cafe Latte');
    F::recipe(12, [$this->coffee->id => 18]);
    $this->deduction = app(StockDeductionService::class);
});

function item(int $itemId, int $productId = 12, float $qty = 1, array $modifiers = []): array
{
    return ['item_id' => $itemId, 'product_id' => $productId, 'name' => 'Produk', 'quantity' => $qty, 'unit_price' => 25000, 'modifiers' => $modifiers];
}

// --- Ketersediaan menu: PATCH /products/{id}/availability ---------------------------------------

it('mengirim ketersediaan lewat PATCH /products/{id}/availability tanpa konfigurasi endpoint', function () {
    Http::fake(['pos.test/api/v1/products/12/availability' => Http::response([
        'success' => true,
        'data' => ['id' => 12, 'is_available' => false, 'availability_source' => 'inventory', 'applied' => true],
    ])]);

    (new PushAvailabilityToPos($this->outlet->id, 12, false))->handle(app(CurrentOutlet::class));

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r->url() === 'https://pos.test/api/v1/products/12/availability'
        && $r['is_available'] === false && $r['source'] === 'inventory'
        && $r->hasHeader('X-Api-Key', 'lkl_test_key'));

    expect(PosProduct::first())->availability_source->toBe('inventory')->auto_unavailable->toBeTrue()->is_available->toBeFalse();
});

it('menyimpan availability_source manual dari POS dan tidak mencoba menandai tersedia lagi', function () {
    $product = PosProduct::first();
    $product->update(['is_available' => false, 'auto_unavailable' => true, 'availability_source' => 'inventory']);

    Http::fake(['*' => Http::response([
        'success' => true,
        'data' => ['id' => 12, 'is_available' => false, 'availability_source' => 'manual', 'applied' => false],
    ])]);

    (new PushAvailabilityToPos($this->outlet->id, 12, true))->handle(app(CurrentOutlet::class));

    expect($product->refresh())->availability_source->toBe('manual')->auto_unavailable->toBeFalse()->is_available->toBeFalse();

    // Stok cukup, tetapi tanda manual tidak disentuh dan tidak ada kiriman baru.
    Queue::fake();
    app(MenuAvailabilityService::class)->refreshAll();
    Queue::assertNotPushed(PushAvailabilityToPos::class);
    expect($product->refresh()->is_available)->toBeFalse();
});

it('menampilkan status habis manual di halaman Resep', function () {
    PosProduct::first()->update(['is_available' => false, 'availability_source' => 'manual']);

    $this->actingAs(F::user($this->outlet, UserRole::Owner))->get('/recipes')->assertSee('Habis (manual di POS)');
});

// --- catalog.updated ------------------------------------------------------------------------------

it('catalog.updated dengan product_ids hanya menghitung ulang ketersediaan produk itu', function () {
    $beans = F::ingredient('Kopi Tipis', 5); // tidak cukup untuk satu porsi
    F::product(13, 'Americano');
    F::product(14, 'Espresso');
    F::recipe(13, [$beans->id => 18]);
    F::recipe(14, [$beans->id => 18]);

    $row = fn ($id, $name) => ['id' => $id, 'name' => $name, 'price' => 20000, 'is_available' => true, 'availability_source' => null, 'modifier_groups' => []];
    Http::fake([
        'pos.test/api/v1/catalog' => Http::response(['success' => true, 'data' => ['outlet' => ['name' => 'Lokal Coffee'], 'products' => [$row(12, 'Cafe Latte'), $row(13, 'Americano'), $row(14, 'Espresso')]]]),
        'pos.test/api/v1/products/*/availability' => Http::response(['success' => true, 'data' => ['id' => 13, 'is_available' => false, 'availability_source' => 'inventory', 'applied' => true]]),
    ]);

    $payload = F::webhook('catalog.updated', 'x', []);
    $payload['idempotency_key'] = 'catalog.updated:1:abc';
    $payload['data'] = ['changed' => ['products'], 'product_ids' => [13]];
    $body = json_encode($payload);

    test()->call('POST', '/webhooks/lokal-pos', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_outlet_a')], $body)->assertStatus(202);

    expect(PosProduct::where('pos_product_id', 13)->first())->is_available->toBeFalse()->auto_unavailable->toBeTrue()
        ->and(PosProduct::where('pos_product_id', 14)->first())->is_available->toBeTrue()->auto_unavailable->toBeFalse();
});

it('sinkron katalog menyimpan availability_source dari POS', function () {
    $catalog = ['outlet' => ['name' => 'X'], 'products' => [
        ['id' => 12, 'name' => 'Cafe Latte', 'price' => 25000, 'is_available' => false, 'availability_source' => 'manual', 'modifier_groups' => []],
    ]];

    app(\App\Services\CatalogSyncService::class)->sync($this->outlet, $catalog);

    expect(PosProduct::first())->availability_source->toBe('manual')->auto_unavailable->toBeFalse()->is_available->toBeFalse();
});

// --- Idempotensi per item_id --------------------------------------------------------------------

it('memakai item_id sebagai source_id dan hanya memotong sekali', function () {
    $order = ['order_id' => 'o-1', 'items' => [item(101), item(102, 12, 2)]];

    $first = $this->deduction->processConfirmed($order);
    $again = $this->deduction->processConfirmed($order);

    expect($first)->toBe(2)->and($again)->toBe(0)
        ->and(StockMovement::where('type', 'sale')->pluck('source_id')->sort()->values()->all())->toBe(['o-1#item:101', 'o-1#item:102'])
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0 - 18 - 36);
});

it('tidak memotong dua kali bila pesanan tercatat dengan format lama lalu datang lagi dengan item_id', function () {
    // Format lama "{uuid}#{index}" (tanpa item_id), mis. dari data sebelum kontrak v2.1.
    $this->deduction->processConfirmed(['order_id' => 'o-2', 'items' => [F::line(12, 1), F::line(12, 1)]]);
    expect(StockMovement::where('type', 'sale')->pluck('source_id')->sort()->values()->all())->toBe(['o-2#0', 'o-2#1']);

    $created = $this->deduction->processConfirmed(['order_id' => 'o-2', 'items' => [item(201), item(202)]]);

    expect($created)->toBe(0)->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0 - 36);
});

it('campuran: satu baris format lama, satu baris baru, void_return tetap berbasis ledger', function () {
    $this->deduction->processConfirmed(['order_id' => 'o-3', 'items' => [F::line(12, 1)]]); // o-3#0
    $created = $this->deduction->processConfirmed(['order_id' => 'o-3', 'items' => [item(301), item(302, 12, 3)]]);

    // Baris pertama sudah tercatat (format lama, indeks 0); baris kedua baru (indeks 1 -> item:302).
    expect($created)->toBe(1)
        ->and(StockMovement::where('type', 'sale')->pluck('source_id')->sort()->values()->all())->toBe(['o-3#0', 'o-3#item:302'])
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0 - 18 - 54);

    $this->deduction->processVoided(['order_id' => 'o-3']);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0)
        ->and(StockMovement::where('type', StockMovementType::VoidReturn->value)->count())->toBe(2);
});

it('memakai modifier_id dari REST (items[].id) dan nama hanya sebagai cadangan', function () {
    ModifierRecipeAdjustment::create(['pos_modifier_id' => 9, 'action' => RecipeAdjustmentAction::Add, 'ingredient_id' => $this->coffee->id, 'qty' => 18]);

    // Bentuk REST v2.1: id baris + modifier_id, nama opsi tidak dikenal di cache katalog.
    $rest = ['id' => 'o-4', 'status' => 'confirmed', 'items' => [[
        'id' => 401, 'product_id' => 12, 'name' => 'Cafe Latte', 'quantity' => 1, 'unit_price' => 33000,
        'modifiers' => [['modifier_id' => 9, 'name' => 'Nama yang tidak ada di cache', 'price_delta' => 8000]],
    ]]];

    $this->deduction->processConfirmed($rest);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0 - 36)
        ->and(StockMovement::where('type', 'sale')->first()->source_id)->toBe('o-4#item:401');
});

// --- Peringatan stok: PUT /stock-alerts -----------------------------------------------------------

it('mengirim daftar lengkap bahan hampir habis lewat PUT /stock-alerts hanya bila daftarnya berubah', function () {
    Http::fake(['pos.test/api/v1/stock-alerts' => Http::response(['success' => true, 'data' => []])]);
    $milk = F::ingredient('Susu', 500, \App\Enums\BaseUnit::Milliliter, min: 200);
    $this->coffee->update(['min_stock' => 100]);
    Http::fake(['pos.test/api/v1/stock-alerts' => Http::response(['success' => true, 'data' => []])]);

    $stock = app(StockService::class);

    // Susu turun ke 150 ≤ 200: masuk daftar.
    $stock->record($milk, StockMovementType::Sale, -350, 'test', 'a');
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === 'https://pos.test/api/v1/stock-alerts'
        && count($r['alerts']) === 1
        && $r['alerts'][0] === ['key' => "ingredient-{$milk->id}", 'name' => 'Susu', 'current' => 150.0, 'min' => 200.0, 'unit' => 'ml']);

    // Pergerakan pada bahan yang tidak ada di daftar tidak mengirim apa pun.
    $stock->record($this->coffee, StockMovementType::Sale, -10, 'test', 'b');
    Http::assertSentCount(1);

    // Daftar sama persis: job dilewati.
    (new PushStockAlerts($this->outlet->id))->handle(app(\App\Services\StockAlertService::class), app(CurrentOutlet::class));
    Http::assertSentCount(1);

    // Stok pulih: daftar kosong dikirim (menghapus peringatan di POS).
    $stock->record($milk, StockMovementType::Purchase, 1000, 'test', 'c');
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['alerts'] === []);
});

it('gagal sementara tidak menandai daftar sebagai terkirim, rekonsiliasi mengirim ulang paksa', function () {
    $milk = F::ingredient('Susu', 100, \App\Enums\BaseUnit::Milliliter, min: 200);

    Http::fake(['pos.test/api/v1/stock-alerts' => Http::sequence()
        ->push(['message' => 'down'], 500)
        ->whenEmpty(Http::response(['success' => true, 'data' => []]))]);
    $job = new PushStockAlerts($this->outlet->id);
    expect(fn () => $job->handle(app(\App\Services\StockAlertService::class), app(CurrentOutlet::class)))->toThrow(\Illuminate\Http\Client\RequestException::class)
        ->and($this->outlet->fresh()->stock_alerts_hash)->toBeNull()
        ->and($job->tries)->toBeGreaterThan(1);

    $job->handle(app(\App\Services\StockAlertService::class), app(CurrentOutlet::class));
    expect($this->outlet->fresh()->stock_alerts_hash)->not->toBeNull();
    Http::assertSentCount(2); // gagal + berhasil

    $job->handle(app(\App\Services\StockAlertService::class), app(CurrentOutlet::class)); // tidak berubah
    Http::assertSentCount(2);

    (new PushStockAlerts($this->outlet->id, force: true))->handle(app(\App\Services\StockAlertService::class), app(CurrentOutlet::class));
    Http::assertSentCount(3);
});

it('rekonsiliasi berkala ikut mengirim peringatan stok', function () {
    Queue::fake();
    Http::fake(['pos.test/api/v1/orders*' => Http::response(['success' => true, 'data' => []])]);

    (new \App\Jobs\ReconcileOrders($this->outlet->id))->handle($this->deduction, app(CurrentOutlet::class));

    Queue::assertPushed(PushStockAlerts::class, fn ($job) => $job->force === true);
});

// --- Pengaturan: scope token --------------------------------------------------------------------

it('tes koneksi menampilkan hasil per scope', function () {
    Http::fake([
        'pos.test/api/v1/catalog' => Http::response(['success' => true, 'data' => []]),
        'pos.test/api/v1/orders*' => Http::response(['success' => true, 'data' => []]),
        'pos.test/api/v1/products/0/availability' => Http::response(['message' => 'forbidden'], 403),
        'pos.test/api/v1/stock-alerts' => Http::response(['message' => 'invalid'], 422),
    ]);

    $this->actingAs(F::user($this->outlet, UserRole::Owner));

    $this->post('/settings/test')
        ->assertSessionHas('error')
        ->assertSessionHas('scope_results', fn ($r) => $r['catalog:read']['ok'] && $r['orders:read']['ok']
            && ! $r['catalog:availability']['ok'] && $r['stock-alerts:write']['ok']);

    $this->followingRedirects()->get('/settings')
        ->assertSee('catalog:availability')->assertSee('stock-alerts:write')
        ->assertSee('Scope belum diberikan ke token ini');
});
