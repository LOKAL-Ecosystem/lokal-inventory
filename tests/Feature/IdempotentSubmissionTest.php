<?php

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\FormToken;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Services\OpnameService;
use Illuminate\Support\Str;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->withoutVite();
    $this->outlet = F::outlet();
    F::as($this->outlet);
    $this->owner = F::user($this->outlet, UserRole::Owner);
    $this->ingredient = F::ingredient('Biji Kopi', 1000);
});

function receiptPayload(int $ingredientId, string $token): array
{
    return [
        '_idempotency_token' => $token,
        'received_at' => now()->toDateString(),
        'items' => [['ingredient_id' => $ingredientId, 'purchase_qty' => 2, 'total_price' => 100000]],
    ];
}

it('stok masuk dengan token yang sama hanya tercatat sekali', function () {
    $this->actingAs($this->owner);
    $token = (string) Str::uuid();

    $first = $this->post('/stock-receipts', receiptPayload($this->ingredient->id, $token));
    $first->assertRedirect();
    $second = $this->post('/stock-receipts', receiptPayload($this->ingredient->id, $token));

    $second->assertRedirect($first->headers->get('Location'));
    expect(StockMovement::where('type', StockMovementType::Purchase->value)->count())->toBe(1)
        ->and($this->ingredient->refresh()->current_stock)->toEqual(3000.0);
});

it('waste dengan token yang sama hanya tercatat sekali', function () {
    $this->actingAs($this->owner);
    $token = (string) Str::uuid();
    $payload = ['_idempotency_token' => $token, 'ingredient_id' => $this->ingredient->id, 'qty' => 100, 'unit' => 'base', 'reason' => 'spilled'];

    $this->post('/waste', $payload)->assertSessionHas('success');
    $this->post('/waste', $payload);

    expect(StockMovement::where('type', StockMovementType::Waste->value)->count())->toBe(1)
        ->and($this->ingredient->refresh()->current_stock)->toEqual(900.0);
});

it('token dilepas bila kiriman gagal validasi, jadi bisa dicoba lagi', function () {
    $this->actingAs($this->owner);
    $token = (string) Str::uuid();

    $this->post('/waste', ['_idempotency_token' => $token, 'ingredient_id' => $this->ingredient->id, 'qty' => 0, 'unit' => 'base', 'reason' => 'spilled'])
        ->assertSessionHasErrors('qty');
    expect(FormToken::count())->toBe(0);

    $this->post('/waste', ['_idempotency_token' => $token, 'ingredient_id' => $this->ingredient->id, 'qty' => 50, 'unit' => 'base', 'reason' => 'spilled'])
        ->assertSessionHas('success');
    expect(StockMovement::where('type', StockMovementType::Waste->value)->count())->toBe(1);
});

it('token milik outlet lain tidak berlaku', function () {
    $other = F::outlet('Outlet Lain');
    F::as($other);
    $otherOwner = F::user($other, UserRole::Owner);
    $otherIngredient = F::ingredient('Biji Kopi', 1000);
    F::as($this->outlet);

    $token = (string) Str::uuid();
    $payload = fn ($id) => ['_idempotency_token' => $token, 'ingredient_id' => $id, 'qty' => 100, 'unit' => 'base', 'reason' => 'spilled'];

    $this->actingAs($this->owner)->post('/waste', $payload($this->ingredient->id));
    F::as($other);
    $this->actingAs($otherOwner)->post('/waste', $payload($otherIngredient->id))->assertSessionHas('success');

    expect($otherIngredient->refresh()->current_stock)->toEqual(900.0)
        ->and(FormToken::withoutGlobalScopes()->where('token', $token)->count())->toBe(2);
});

it('dua request selesaikan opname bersamaan hanya membuat adjustment sekali', function () {
    $service = app(OpnameService::class);
    $opname = $service->start(now()->toDateString(), null, $this->owner->id);
    $item = $opname->items()->first();
    $service->saveCounts($opname, [$item->id => 800]);

    // Dua request memegang salinan opname yang sama-sama masih draft.
    $copyA = StockOpname::find($opname->id);
    $copyB = StockOpname::find($opname->id);

    $service->complete($copyA, $this->owner->id);
    expect(fn () => $service->complete($copyB, $this->owner->id))->toThrow(RuntimeException::class);

    expect(StockMovement::where('type', StockMovementType::Adjustment->value)->where('source_type', 'opname_item')->count())->toBe(1)
        ->and($this->ingredient->refresh()->current_stock)->toEqual(800.0);
});

it('simpan hitungan ditolak setelah opname selesai', function () {
    $service = app(OpnameService::class);
    $opname = $service->start(now()->toDateString());
    $stale = StockOpname::find($opname->id);
    $service->complete($opname);

    expect(fn () => $service->saveCounts($stale, [1 => 5]))->toThrow(RuntimeException::class);
});

it('permintaan fetch menerima redirect sebagai JSON dan kiriman ganda mengarah ke hasil pertama', function () {
    $this->actingAs($this->owner);
    $token = (string) Str::uuid();
    $headers = ['X-Form-Fetch' => '1', 'Accept' => 'application/json'];

    $first = $this->post('/stock-receipts', receiptPayload($this->ingredient->id, $token), $headers)->assertOk()->assertJsonStructure(['redirect']);
    $this->post('/stock-receipts', receiptPayload($this->ingredient->id, $token), $headers)
        ->assertOk()->assertJson(['redirect' => $first->json('redirect')]);

    expect(StockMovement::where('type', StockMovementType::Purchase->value)->count())->toBe(1);
});

it('form stok memuat token idempotensi dan tombol yang butuh koneksi', function () {
    $this->actingAs($this->owner);

    foreach (['/stock-receipts/create', '/waste', '/opnames'] as $uri) {
        $this->get($uri)->assertSee('name="_idempotency_token"', false)->assertSee('data-requires-online', false);
    }
});

it('token lama dibersihkan oleh scheduler', function () {
    FormToken::create(['token' => (string) Str::uuid(), 'action' => 'x']);
    FormToken::query()->update(['created_at' => now()->subDays(3)]);
    FormToken::create(['token' => (string) Str::uuid(), 'action' => 'y']);

    $this->artisan('schedule:test', ['--name' => 'inventory-prune-form-tokens'])->assertSuccessful();

    expect(FormToken::count())->toBe(1);
});
