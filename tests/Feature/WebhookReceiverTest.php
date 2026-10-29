<?php

use App\Jobs\ProcessConfirmedOrder;
use App\Models\StockMovement;
use App\Models\WebhookInbox;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InventoryFactory as F;

beforeEach(function () {
    $this->outlet = F::outlet('Lokal Coffee', 'whsec_outlet_a');
    F::as($this->outlet);

    $this->coffee = F::ingredient('Biji Kopi', 1000);
    F::product(12, 'Cafe Latte');
    F::recipe(12, [$this->coffee->id => 18]);
});

function postWebhook(string $uri, array $payload, ?string $secret = 'whsec_outlet_a', array $headers = []): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload);
    $signature = $secret ? ['HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, $secret)] : [];

    return test()->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'application/json'] + $signature + $headers, $body);
}

it('menerima webhook order.confirmed, menyimpan di inbox, dan memotong stok', function () {
    $payload = F::webhook('order.confirmed', 'ord-1', [F::line(12, 2)]);

    postWebhook('/webhooks/lokal-pos', $payload)->assertStatus(202);

    $inbox = WebhookInbox::first();
    expect($inbox->event_id)->toBe('order.confirmed:ord-1')
        ->and($inbox->processed_at)->not->toBeNull()
        ->and($inbox->last_error)->toBeNull()
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(964.0);
});

it('menolak signature yang salah, hilang, atau dari secret lain', function () {
    $payload = F::webhook('order.confirmed', 'ord-2', [F::line(12)]);

    postWebhook('/webhooks/lokal-pos', $payload, 'whsec_salah')->assertStatus(401);
    postWebhook('/webhooks/lokal-pos', $payload, null)->assertStatus(401);

    expect(WebhookInbox::count())->toBe(0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('menolak bila body diubah setelah ditandatangani', function () {
    $payload = F::webhook('order.confirmed', 'ord-3', [F::line(12)]);
    $signature = hash_hmac('sha256', json_encode($payload), 'whsec_outlet_a');
    $payload['data']['items'][0]['quantity'] = 50;

    $this->call('POST', '/webhooks/lokal-pos', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $signature], json_encode($payload))
        ->assertStatus(401);
});

it('mengabaikan webhook ganda dengan idempotency_key sama', function () {
    $payload = F::webhook('order.confirmed', 'ord-4', [F::line(12, 1)]);

    postWebhook('/webhooks/lokal-pos', $payload)->assertStatus(202);
    postWebhook('/webhooks/lokal-pos', $payload)->assertOk()->assertJson(['duplicate' => true]);

    expect(WebhookInbox::count())->toBe(1)
        ->and(StockMovement::count())->toBe(2) // seed + 1 sale
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(982.0);
});

it('tetap aman bila inbox yang sama diproses ulang', function () {
    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-5', [F::line(12, 1)]));

    WebhookInbox::first()->update(['processed_at' => null]);
    ProcessConfirmedOrder::dispatchSync($this->outlet->id, WebhookInbox::first()->id);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(982.0);
});

it('membalas cepat dan memproses lewat queue', function () {
    Queue::fake();

    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-6', [F::line(12, 1)]))->assertStatus(202);

    Queue::assertPushed(ProcessConfirmedOrder::class);
    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('order.voided mengembalikan stok yang dipotong', function () {
    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-7', [F::line(12, 3)]));
    expect((float) $this->coffee->fresh()->current_stock)->toBe(946.0);

    postWebhook('/webhooks/lokal-pos', F::webhook('order.voided', 'ord-7', [], 'voided'))->assertStatus(202);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('menangani void yang datang sebelum confirmed (tidak berurutan)', function () {
    Queue::fake();
    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-8', [F::line(12, 1)]));
    postWebhook('/webhooks/lokal-pos', F::webhook('order.voided', 'ord-8', [], 'voided'));
    Queue::assertPushed(ProcessConfirmedOrder::class);

    // Worker memproses void lebih dulu.
    $void = WebhookInbox::where('event_type', 'order.voided')->first();
    \App\Jobs\ProcessVoidedOrder::dispatchSync($this->outlet->id, $void->id);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);

    // Lalu confirmed menyusul: tidak memotong lagi.
    $confirmed = WebhookInbox::where('event_type', 'order.confirmed')->first();
    ProcessConfirmedOrder::dispatchSync($this->outlet->id, $confirmed->id);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('event tanpa efek stok (order.created, payment.paid, cancelled) disimpan tanpa memotong', function () {
    foreach (['order.created', 'payment.paid', 'order.cancelled'] as $type) {
        postWebhook('/webhooks/lokal-pos', F::webhook($type, 'ord-9', [F::line(12, 5)]))->assertStatus(202);
    }

    expect(WebhookInbox::count())->toBe(3)
        ->and(WebhookInbox::whereNull('processed_at')->count())->toBe(0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('mencatat last_error bila pemrosesan gagal, dan tetap membalas 2xx', function () {
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response('x', 500)]);

    // void tanpa sale dan tanpa API yang bisa dihubungi
    postWebhook('/webhooks/lokal-pos', F::webhook('order.voided', 'ord-10', [], 'voided'))->assertStatus(202);

    $inbox = WebhookInbox::first();
    expect($inbox->processed_at)->toBeNull()->and($inbox->last_error)->not->toBeNull()->and($inbox->attempts)->toBe(1);
});

it('route kompatibilitas /api/webhooks/pos dan /api/v1/webhooks/pos tetap berfungsi', function () {
    postWebhook('/api/webhooks/pos', F::webhook('order.confirmed', 'ord-11', [F::line(12, 1)]))->assertStatus(202);
    postWebhook('/api/v1/webhooks/pos', F::webhook('order.confirmed', 'ord-12', [F::line(12, 1)]))->assertStatus(202);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(964.0);
});

it('menentukan outlet dari signature, bukan dari isi payload', function () {
    $other = F::outlet('Outlet B', 'whsec_outlet_b');
    F::as($other);
    $beans = F::ingredient('Biji Kopi', 500);
    F::product(12, 'Cafe Latte');
    F::recipe(12, [$beans->id => 18]);

    // Payload mengaku outlet_id lain, tapi ditandatangani secret outlet B.
    $payload = F::webhook('order.confirmed', 'ord-13', [F::line(12, 1)]);
    $payload['outlet_id'] = $this->outlet->pos_outlet_id;
    postWebhook('/webhooks/lokal-pos', $payload, 'whsec_outlet_b')->assertStatus(202);

    F::as($this->outlet);
    expect((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
    F::as($other);
    expect((float) $beans->fresh()->current_stock)->toBe(482.0);
});

// --- URL unik per outlet --------------------------------------------------------------------------

it('menerima webhook di URL unik outlet dan menentukan outlet dari token', function () {
    $other = F::outlet('Outlet Lain', 'whsec_outlet_b');
    $payload = F::webhook('order.confirmed', 'ord-tok-1', [F::line(12, 1)]);

    postWebhook("/webhooks/lokal-pos/{$this->outlet->webhook_token}", $payload)->assertStatus(202);

    expect(WebhookInbox::withoutGlobalScopes()->first()->outlet_id)->toBe($this->outlet->id)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(982.0)
        ->and($this->outlet->webhook_token)->not->toBe($other->webhook_token)
        ->and(strlen($this->outlet->webhook_token))->toBe(40);
});

it('menolak token tidak dikenal, atau signature dari secret outlet lain', function () {
    F::outlet('Outlet Lain', 'whsec_outlet_b');
    $payload = F::webhook('order.confirmed', 'ord-tok-2', [F::line(12, 1)]);

    postWebhook('/webhooks/lokal-pos/token-ngawur', $payload)->assertStatus(401);
    // Token outlet A dengan signature secret outlet B tidak lolos.
    postWebhook("/webhooks/lokal-pos/{$this->outlet->webhook_token}", $payload, 'whsec_outlet_b')->assertStatus(401);

    expect(WebhookInbox::withoutGlobalScopes()->count())->toBe(0)
        ->and((float) $this->coffee->fresh()->current_stock)->toBe(1000.0);
});

it('URL lama tanpa token tetap berfungsi', function () {
    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-tok-3', [F::line(12, 1)]))->assertStatus(202);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(982.0);
});

it('URL webhook outlet yang dikirim ke POS saat penautan menerima event', function () {
    expect($this->outlet->webhookUrl())->toEndWith("/webhooks/lokal-pos/{$this->outlet->webhook_token}");

    postWebhook(parse_url($this->outlet->webhookUrl(), PHP_URL_PATH), F::webhook('order.confirmed', 'ord-tok-4', [F::line(12, 1)]))->assertStatus(202);
});

it('webhook dikecualikan dari CSRF, juga saat pengecekan CSRF aktif', function () {
    // Di environment "testing" middleware CSRF melewati semua request; aktifkan pengecekannya.
    $this->app['env'] = 'local';

    postWebhook('/webhooks/lokal-pos', F::webhook('order.confirmed', 'ord-csrf-1', [F::line(12, 1)]))->assertStatus(202);
    postWebhook("/webhooks/lokal-pos/{$this->outlet->webhook_token}", F::webhook('order.confirmed', 'ord-csrf-2', [F::line(12, 1)]))->assertStatus(202);

    expect((float) $this->coffee->fresh()->current_stock)->toBe(964.0);
});
