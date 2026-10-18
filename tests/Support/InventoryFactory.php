<?php

namespace Tests\Support;

use App\Enums\BaseUnit;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\CurrentOutlet;

/**
 * Pembantu pembuat data uji. Semua dibuat dalam konteks outlet yang eksplisit.
 */
class InventoryFactory
{
    public static function outlet(string $name = 'Lokal Coffee', ?string $secret = 'whsec_test'): Outlet
    {
        return Outlet::create([
            'name' => $name,
            'pos_outlet_id' => random_int(1, 1_000_000),
            'pos_base_url' => 'https://pos.test',
            'pos_api_key' => 'lkl_test_key',
            'webhook_secret' => $secret,
        ]);
    }

    public static function user(Outlet $outlet, UserRole $role = UserRole::Owner): User
    {
        return User::create([
            'name' => ucfirst($role->value) . ' ' . $outlet->name,
            'email' => uniqid($role->value . '_') . '@lokal.test',
            'password' => 'password',
            'role' => $role,
            'outlet_id' => $outlet->id,
        ]);
    }

    public static function ingredient(string $name, float $stock = 0, BaseUnit $unit = BaseUnit::Gram, float $min = 0, float $cost = 0): Ingredient
    {
        $ingredient = Ingredient::create([
            'name' => $name,
            'base_unit' => $unit,
            'min_stock' => $min,
            'purchase_unit' => 'pack',
            'purchase_unit_qty' => 1000,
        ]);

        if ($stock != 0) {
            app(StockService::class)->record($ingredient, StockMovementType::Adjustment, $stock, 'test_seed', uniqid());
        }

        if ($cost > 0) {
            $ingredient->forceFill(['avg_cost' => $cost])->save();
        }

        return $ingredient->refresh();
    }

    public static function product(int $posId, string $name = 'Produk', int $price = 25000): PosProduct
    {
        return PosProduct::create(['pos_product_id' => $posId, 'name' => $name, 'price' => $price]);
    }

    /**
     * @param  array<int, float>  $items  ingredient_id => qty
     */
    public static function recipe(int $posProductId, array $items): void
    {
        foreach ($items as $ingredientId => $qty) {
            RecipeItem::create(['pos_product_id' => $posProductId, 'ingredient_id' => $ingredientId, 'qty' => $qty]);
        }
    }

    public static function as(Outlet $outlet): void
    {
        app(CurrentOutlet::class)->set($outlet);
    }

    /**
     * Payload webhook sesuai WEBHOOK_CONTRACT.md v2.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function webhook(string $type, string $orderId, array $items = [], string $status = 'confirmed'): array
    {
        return [
            'event_type' => $type,
            'idempotency_key' => "{$type}:{$orderId}",
            'timestamp' => now()->toIso8601String(),
            'outlet_id' => 1,
            'data' => [
                'order_id' => $orderId,
                'code' => '012',
                'status' => $status,
                'items' => $items,
                'total' => 41800,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $modifierIds
     * @return array<string, mixed>
     */
    public static function line(int $productId, float $qty = 1, array $modifierIds = []): array
    {
        return [
            'product_id' => $productId,
            'name' => 'Produk',
            'quantity' => $qty,
            'unit_price' => 25000,
            'modifiers' => array_map(fn ($id) => ['modifier_id' => $id, 'name' => "Opsi {$id}", 'price_delta' => 0], $modifierIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function signedHeaders(array $payload, string $secret = 'whsec_test'): array
    {
        $body = json_encode($payload);

        return [
            'X-Webhook-Signature' => hash_hmac('sha256', $body, $secret),
            'X-Idempotency-Key' => $payload['idempotency_key'],
            'X-Event-Type' => $payload['event_type'],
        ];
    }
}
