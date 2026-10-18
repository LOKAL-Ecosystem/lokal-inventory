<?php

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\Outlet;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->withoutVite();
    $this->outlet = F::outlet();
    F::as($this->outlet);
    $this->owner = F::user($this->outlet, UserRole::Owner);
    $this->cashier = F::user($this->outlet, UserRole::Cashier);
});

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/login')->assertOk()->assertSee('Lokal Stock');
});

it('login dan logout bekerja', function () {
    $this->post('/login', ['email' => $this->owner->email, 'password' => 'salah'])->assertSessionHasErrors('email');

    $this->post('/login', ['email' => $this->owner->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->owner);

    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('pemilik dapat membuka semua halaman utama', function () {
    $this->actingAs($this->owner);
    F::product(1, 'Americano');

    foreach (['/', '/ingredients', '/recipes', '/recipes/1', '/stock-receipts', '/stock-receipts/create', '/waste', '/opnames', '/suppliers', '/reports', '/settings'] as $uri) {
        $this->get($uri)->assertOk();
    }
});

it('kasir hanya bisa membuka halaman operasional', function () {
    $this->actingAs($this->cashier);

    foreach (['/', '/ingredients', '/stock-receipts', '/stock-receipts/create', '/waste', '/opnames'] as $uri) {
        $this->get($uri)->assertOk();
    }

    foreach (['/recipes', '/suppliers', '/reports', '/settings'] as $uri) {
        $this->get($uri)->assertForbidden();
    }

    $this->post('/ingredients', ['name' => 'X', 'base_unit' => 'g', 'purchase_unit_qty' => 1, 'min_stock' => 0])->assertForbidden();
});

it('kasir tidak melihat HPP, nilai stok, atau biaya rata-rata', function () {
    F::ingredient('Biji Kopi', 1000, cost: 123);

    $this->actingAs($this->cashier)->get('/')->assertDontSee('Nilai stok');
    $this->get('/ingredients')->assertDontSee('Biaya rata-rata');

    $this->actingAs($this->owner)->get('/')->assertSee('Nilai stok');
    $this->get('/ingredients')->assertSee('Biaya rata-rata');
});

it('kasir tidak bisa menyelesaikan opname tetapi bisa mengisi hitungan', function () {
    F::ingredient('Kopi', 100);
    $opname = app(\App\Services\OpnameService::class)->start(now()->toDateString());
    $item = $opname->items->first();

    $this->actingAs($this->cashier)
        ->put("/opnames/{$opname->id}", ['counts' => [$item->id => 90]])->assertRedirect();
    expect((float) $item->fresh()->counted_qty)->toBe(90.0);

    $this->post("/opnames/{$opname->id}/complete")->assertForbidden();
    expect($opname->fresh()->isCompleted())->toBeFalse();

    $this->actingAs($this->owner)->post("/opnames/{$opname->id}/complete")->assertRedirect();
    expect($opname->fresh()->isCompleted())->toBeTrue();
});

it('kasir dapat mencatat stok masuk dan waste', function () {
    $beans = F::ingredient('Kopi', 0);
    $this->actingAs($this->cashier);

    $this->post('/stock-receipts', [
        'received_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $beans->id, 'purchase_qty' => 2, 'total_price' => 200000]],
    ])->assertRedirect();
    expect((float) $beans->fresh()->current_stock)->toBe(2000.0);

    $this->post('/waste', ['ingredient_id' => $beans->id, 'qty' => 100, 'unit' => 'base', 'reason' => 'spilled'])->assertRedirect();
    expect((float) $beans->fresh()->current_stock)->toBe(1900.0);

    $movements = \App\Models\StockMovement::where('created_by', $this->cashier->id)->count();
    expect($movements)->toBe(2); // created_by tercatat
});

it('tidak bisa memakai bahan milik outlet lain di form', function () {
    $other = F::outlet('Outlet B', 'whsec_b');
    F::as($other);
    $foreign = F::ingredient('Kopi B', 100);
    F::as($this->outlet);

    $this->actingAs($this->cashier)->post('/stock-receipts', [
        'received_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $foreign->id, 'purchase_qty' => 1, 'total_price' => 1000]],
    ])->assertSessionHasErrors('items.0.ingredient_id');

    $this->get("/ingredients/{$foreign->id}")->assertNotFound();
});

it('bahan yang sudah punya riwayat dinonaktifkan, bukan dihapus', function () {
    $beans = F::ingredient('Kopi', 100);

    $this->actingAs($this->owner)->delete("/ingredients/{$beans->id}")->assertRedirect();

    expect(Ingredient::find($beans->id))->not->toBeNull()->and(Ingredient::find($beans->id)->is_active)->toBeFalse();
});

it('pemilik dapat menyimpan resep dan penyesuaian opsi', function () {
    $beans = F::ingredient('Kopi', 100);
    $oat = F::ingredient('Oat', 100);
    $milk = F::ingredient('Susu', 100);
    F::product(1, 'Latte');
    \App\Models\PosModifier::create(['pos_modifier_id' => 5, 'pos_group_id' => 1, 'name' => 'Oat Milk']);
    $this->actingAs($this->owner);

    $this->put('/recipes/1', ['items' => [
        ['ingredient_id' => $beans->id, 'qty' => 18],
        ['ingredient_id' => $milk->id, 'qty' => 150],
    ]])->assertRedirect();
    expect(\App\Models\RecipeItem::count())->toBe(2);

    $this->post('/recipes/1/adjustments', ['pos_modifier_id' => 5, 'action' => 'replace', 'ingredient_id' => $oat->id, 'replaces_ingredient_id' => $milk->id])->assertRedirect();
    $this->post('/recipes/1/adjustments', ['pos_modifier_id' => 5, 'action' => 'multiply', 'factor' => ''])->assertSessionHasErrors('factor');

    expect(\App\Models\ModifierRecipeAdjustment::count())->toBe(1);

    $this->getJson('/recipes/1/preview?modifier_ids[]=5')->assertOk()->assertJsonPath('usage.1.ingredient', 'Oat');
});

it('rahasia koneksi POS disimpan terenkripsi dan tidak ditampilkan utuh', function () {
    $this->actingAs($this->owner)->put('/settings', [
        'name' => 'Lokal Coffee',
        'pos_base_url' => 'https://pos.contoh.com',
        'pos_api_key' => 'lkl_super_rahasia_1234',
        'webhook_secret' => 'whsec_abcdef9999',
    ])->assertRedirect();

    $raw = \DB::table('outlets')->where('id', $this->outlet->id)->first();
    expect($raw->pos_api_key)->not->toContain('lkl_super')->and($raw->webhook_secret)->not->toContain('whsec_abc');

    $this->get('/settings')->assertOk()->assertDontSee('lkl_super_rahasia_1234')->assertSee('1234')->assertDontSee('whsec_abcdef9999');

    // Kosong = tidak diubah.
    $this->put('/settings', ['name' => 'Lokal Coffee', 'pos_api_key' => '', 'webhook_secret' => ''])->assertRedirect();
    expect(Outlet::find($this->outlet->id)->pos_api_key)->toBe('lkl_super_rahasia_1234');
});

it('tes koneksi dan sinkron katalog memakai API Lokal POS', function () {
    \Illuminate\Support\Facades\Http::fake([
        'pos.test/api/v1/catalog' => \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => [
            'outlet' => ['name' => 'Lokal Coffee'],
            'products' => [[
                'id' => 12, 'name' => 'Cafe Latte', 'category' => 'Coffee', 'price' => 38000, 'is_available' => true,
                'modifier_groups' => [['id' => 3, 'name' => 'Ukuran', 'modifiers' => [['id' => 5, 'name' => 'Large', 'price_delta' => 5000]]]],
            ]],
        ]]),
        'pos.test/api/v1/orders*' => \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => []]),
        'pos.test/api/v1/products/0/availability' => \Illuminate\Support\Facades\Http::response(['message' => 'not found'], 404),
        'pos.test/api/v1/stock-alerts' => \Illuminate\Support\Facades\Http::response(['message' => 'invalid'], 422),
    ]);
    $this->actingAs($this->owner);

    $this->post('/settings/test')->assertSessionHas('success');
    $this->post('/settings/sync')->assertSessionHas('success');

    expect(\App\Models\PosProduct::where('pos_product_id', 12)->first()->price)->toBe(38000)
        ->and(\App\Models\PosModifier::where('pos_modifier_id', 5)->first()->name)->toBe('Large')
        ->and($this->outlet->fresh()->last_catalog_sync_at)->not->toBeNull();
});

it('laporan dapat diekspor ke CSV dan PDF', function () {
    F::ingredient('Biji Kopi', 100, cost: 50);
    $this->actingAs($this->owner);

    $csv = $this->get('/reports/export?type=stock_value&format=csv');
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Biji Kopi');

    $this->get('/reports/export?type=movements&format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

    foreach (array_keys(\App\Services\ReportService::TYPES) as $type) {
        $this->get("/reports?type={$type}")->assertOk();
    }
});
