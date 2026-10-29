<?php

use App\Enums\UserRole;
use App\Jobs\ReconcileOrders;
use App\Jobs\SyncCatalogFromPos;
use App\Models\Outlet;
use App\Models\User;
use App\Support\LinkSignature;
use Illuminate\Support\Facades\Bus;
use Tests\Support\InventoryFactory as F;

/*
 * Penautan otomatis & satu login dari Lokal POS (kontrak v2.3).
 */

beforeEach(function () {
    $this->withoutVite();
    config(['lokal.link.secret' => 'test-link-secret', 'lokal.link.pos_url' => 'https://pos.test']);
    Bus::fake([SyncCatalogFromPos::class, ReconcileOrders::class]);
});

function linkPayload(array $overrides = []): array
{
    return array_merge([
        'pos_outlet_id' => 7,
        'name' => 'Lokal Coffee Dago',
        'pos_base_url' => 'https://pos.test',
        'api_token' => 'lkl_api_token_baru',
        'webhook_secret' => 'whsec_link_secret',
        'staff' => [
            ['pos_user_id' => 11, 'name' => 'Owner Dago', 'email' => 'owner@dago.test', 'role' => 'owner', 'is_active' => true],
            ['pos_user_id' => 12, 'name' => 'Kasir Dago', 'email' => 'kasir@dago.test', 'role' => 'cashier', 'is_active' => true],
        ],
    ], $overrides);
}

function postLink(array $payload, ?string $secret = 'test-link-secret', ?int $timestamp = null): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload);
    $timestamp ??= time();
    $headers = $secret === null ? [] : [
        'HTTP_X_LOKAL_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_LOKAL_SIGNATURE' => LinkSignature::sign($body, $timestamp, $secret),
    ];

    return test()->call('POST', '/link/outlets', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'] + $headers, $body);
}

function ssoToken(array $overrides = [], ?int $now = null): string
{
    return LinkSignature::makeToken(array_merge([
        'pos_outlet_id' => 7, 'pos_user_id' => 11, 'name' => 'Owner Dago', 'email' => 'owner@dago.test', 'role' => 'owner', 'is_active' => true,
    ], $overrides), $now);
}

it('menerima tanda tangan yang benar dan menolak yang salah, hilang, atau kedaluwarsa', function () {
    postLink(linkPayload(), 'secret-lain')->assertStatus(401);
    postLink(linkPayload(), null)->assertStatus(401);
    postLink(linkPayload(), timestamp: time() - 301)->assertStatus(401);
    postLink(linkPayload(), timestamp: time() + 301)->assertStatus(401);
    expect(Outlet::where('pos_outlet_id', 7)->exists())->toBeFalse();

    postLink(linkPayload(), timestamp: time() - 299)->assertOk();
});

it('body yang diubah setelah ditandatangani ditolak', function () {
    $body = json_encode(linkPayload());
    $timestamp = time();
    $signature = LinkSignature::sign($body, $timestamp, 'test-link-secret');

    $this->call('POST', '/link/outlets', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_LOKAL_TIMESTAMP' => (string) $timestamp, 'HTTP_X_LOKAL_SIGNATURE' => $signature,
    ], json_encode(linkPayload(['pos_outlet_id' => 8])))->assertStatus(401);
});

it('penautan idempoten pada pos_outlet_id: dua kali tetap satu outlet, rahasia terenkripsi', function () {
    $first = postLink(linkPayload())->assertOk();
    $outlet = Outlet::where('pos_outlet_id', 7)->sole();

    $first->assertExactJson(['inventory_outlet_id' => $outlet->id, 'webhook_url' => $outlet->webhookUrl()]);
    expect($outlet->pos_api_key)->toBe('lkl_api_token_baru')
        ->and($outlet->webhook_secret)->toBe('whsec_link_secret')
        ->and($outlet->linked_at)->not->toBeNull()
        ->and(\DB::table('outlets')->where('id', $outlet->id)->value('pos_api_key'))->not->toContain('lkl_api');

    postLink(linkPayload(['name' => 'Lokal Coffee Dago Baru', 'api_token' => 'lkl_token_kedua']))
        ->assertOk()->assertJsonPath('inventory_outlet_id', $outlet->id)->assertJsonPath('webhook_url', $outlet->webhookUrl());

    expect(Outlet::where('pos_outlet_id', 7)->count())->toBe(1)
        ->and($outlet->fresh()->name)->toBe('Lokal Coffee Dago Baru')
        ->and($outlet->fresh()->pos_api_key)->toBe('lkl_token_kedua')
        ->and(User::where('outlet_id', $outlet->id)->count())->toBe(2);

    Bus::assertDispatched(SyncCatalogFromPos::class, fn ($job) => $job->outletId === $outlet->id);
    Bus::assertDispatched(ReconcileOrders::class, fn ($job) => $job->outletId === $outlet->id);
});

it('webhook dari POS diterima di URL yang dikirim saat penautan', function () {
    $url = postLink(linkPayload())->json('webhook_url');
    $outlet = Outlet::where('pos_outlet_id', 7)->sole();
    $payload = F::webhook('catalog.updated', 'cat-1');
    $body = json_encode($payload);

    $this->call('POST', parse_url($url, PHP_URL_PATH), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, 'whsec_link_secret'),
    ], $body)->assertStatus(202);

    expect(\App\Models\WebhookInbox::withoutGlobalScopes()->where('outlet_id', $outlet->id)->count())->toBe(1);
});

it('outlet lain tidak tersentuh, dan akun demo dengan email sama di outlet itu diambil alih', function () {
    $other = F::outlet('Outlet Lain');
    $other->forceFill(['pos_outlet_id' => 99])->save();
    $otherUser = F::user($other, UserRole::Owner);
    $otherBefore = $other->fresh()->only(['name', 'pos_base_url', 'pos_api_key', 'webhook_secret', 'linked_at']);

    $demo = F::outlet('Lokal Coffee');
    $demo->forceFill(['pos_outlet_id' => 7])->save();
    $demoOwner = User::create(['name' => 'Owner Demo', 'email' => 'owner@dago.test', 'password' => 'password', 'role' => UserRole::Owner, 'outlet_id' => $demo->id]);

    postLink(linkPayload(['staff' => [
        ['pos_user_id' => 11, 'name' => 'Owner Dago', 'email' => 'owner@dago.test', 'role' => 'owner', 'is_active' => true],
        // Email sudah dipakai user outlet lain: user cermin mendapat alamat pengganti, user lain tidak berubah.
        ['pos_user_id' => 13, 'name' => 'Kasir Bentrok', 'email' => $otherUser->email, 'role' => 'cashier', 'is_active' => true],
    ]]))->assertOk();

    expect(Outlet::count())->toBe(2)
        ->and($other->fresh()->only(array_keys($otherBefore)))->toEqual($otherBefore)
        ->and($otherUser->fresh()->outlet_id)->toBe($other->id)
        ->and($demoOwner->fresh()->pos_user_id)->toBe(11)
        ->and($demoOwner->fresh()->name)->toBe('Owner Dago')
        ->and(User::where('outlet_id', $demo->id)->where('pos_user_id', 13)->value('email'))->toBe('pos7-13@users.lokal.invalid');
});

it('satu login: token sah masuk ke dashboard dan membuat/memperbarui user cermin', function () {
    postLink(linkPayload())->assertOk();

    $this->get('/sso?token=' . ssoToken())->assertRedirect(route('dashboard'));
    $owner = User::where('pos_user_id', 11)->sole();
    $this->assertAuthenticatedAs($owner);
    expect($owner->password)->toBeNull()->and($owner->role)->toBe(UserRole::Owner);

    // Nama, email, dan peran terbaru dari POS ikut terbawa.
    app('auth')->forgetGuards();
    $this->get('/sso?token=' . ssoToken(['name' => 'Owner Ganti Nama', 'role' => 'cashier', 'email' => 'baru@dago.test']))->assertRedirect(route('dashboard'));
    $owner->refresh();
    expect($owner->name)->toBe('Owner Ganti Nama')->and($owner->role)->toBe(UserRole::Cashier)->and($owner->email)->toBe('baru@dago.test')
        ->and(User::where('pos_user_id', 11)->count())->toBe(1);

    // Staf baru yang belum ada di daftar penautan dibuat saat pertama masuk.
    app('auth')->forgetGuards();
    $this->get('/sso?token=' . ssoToken(['pos_user_id' => 20, 'name' => 'Kasir Baru', 'email' => 'kasir.baru@dago.test', 'role' => 'cashier']))->assertRedirect(route('dashboard'));
    expect(User::where('pos_user_id', 20)->sole()->role)->toBe(UserRole::Cashier);
});

it('token satu login yang dipakai ulang, kedaluwarsa, atau diubah ditolak', function () {
    postLink(linkPayload())->assertOk();

    $token = ssoToken();
    $this->get('/sso?token=' . $token)->assertRedirect(route('dashboard'));
    app('auth')->forgetGuards();
    $this->flushSession();
    $this->get('/sso?token=' . $token)->assertRedirect(route('login'))->assertSessionHas('error');
    $this->assertGuest();

    $this->get('/sso?token=' . ssoToken(now: time() - 61))->assertRedirect(route('login'));
    $this->assertGuest();

    [$encoded, $signature] = explode('.', ssoToken());
    $forged = LinkSignature::base64UrlEncode(json_encode(['pos_outlet_id' => 7, 'pos_user_id' => 12, 'name' => 'x', 'email' => 'x@x.test', 'role' => 'owner', 'exp' => time() + 60, 'nonce' => 'n']));
    $this->get("/sso?token={$forged}.{$signature}")->assertRedirect(route('login'));
    $this->get('/sso?token=' . LinkSignature::makeToken(['pos_outlet_id' => 404, 'pos_user_id' => 1, 'name' => 'x', 'email' => 'x@x.test']))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('staf yang dinonaktifkan di POS tidak bisa masuk dan sesinya diputus', function () {
    postLink(linkPayload())->assertOk();
    $cashier = User::where('pos_user_id', 12)->sole();

    // Masuk lewat sesi sungguhan, supaya tiap request membaca user dari database seperti di aplikasi.
    $this->get('/sso?token=' . ssoToken(['pos_user_id' => 12, 'name' => 'Kasir Dago', 'email' => 'kasir@dago.test', 'role' => 'cashier']));
    app('auth')->forgetGuards();
    $this->get('/')->assertOk();

    postLink(linkPayload(['staff' => [['pos_user_id' => 12, 'name' => 'Kasir Dago', 'email' => 'kasir@dago.test', 'role' => 'cashier', 'is_active' => false]]]))->assertOk();
    expect($cashier->fresh()->is_active)->toBeFalse();

    app('auth')->forgetGuards();
    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->get('/sso?token=' . ssoToken(['pos_user_id' => 12, 'email' => 'kasir@dago.test', 'role' => 'cashier', 'is_active' => false]))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('halaman login menjadi tombol Masuk lewat Lokal POS; form password ditutup di luar APP_ENV=local', function () {
    $this->get('/login')->assertOk()
        ->assertSee('Masuk lewat Lokal POS')
        ->assertSee('https://pos.test/go/stock', false)
        ->assertDontSee('name="password"', false);

    $user = F::user(F::outlet());
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertNotFound();
    $this->assertGuest();
});

it('tautan Kembali ke POS tampil di sidebar', function () {
    postLink(linkPayload())->assertOk();
    $this->actingAs(User::where('pos_user_id', 11)->sole())->get('/')->assertOk()->assertSee('Kembali ke POS')->assertSee('https://pos.test', false);
});

it('penautan mati dengan tenang bila secret kosong', function () {
    config(['lokal.link.secret' => null]);

    postLink(linkPayload(), 'apa-saja')->assertNotFound();
    $this->get('/sso?token=apa-saja')->assertNotFound();
    expect(LinkSignature::enabled())->toBeFalse()->and(Outlet::count())->toBe(0);

    $this->get('/login')->assertOk()->assertDontSee('Masuk lewat Lokal POS')->assertSee('name="password"', false);
    $user = F::user(F::outlet());
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
});
