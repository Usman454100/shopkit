<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ProductExpired;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CatalogInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentStore::clear();
        parent::tearDown();
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function makeStoreWithAdmin(): array
    {
        $store = CurrentStore::bypass(function () {
            $store = Store::factory()->create();
            $store->createDomain($store->slug.'.'.config('tenancy.central_domain'));

            return $store;
        });

        $owner = User::factory()->orgOwner()->create(['store_id' => $store->id]);

        $domain = $store->slug.'.'.config('tenancy.central_domain');

        return [$store, $domain, $owner];
    }

    private function apiUrl(string $domain, string $path): string
    {
        return "http://{$domain}/api/{$path}";
    }

    public function test_a_fixed_price_product_can_be_created(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Rice 5kg Bag',
                'pricing_type' => 'fixed',
                'base_price' => 1200,
                'unit' => 'pcs',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Rice 5kg Bag', 'store_id' => $store->id]);
    }

    public function test_weight_based_products_are_restricted_to_kg_or_g_units(): void
    {
        [, $domain, $owner] = $this->makeStoreWithAdmin();

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Tomatoes',
                'pricing_type' => 'weight_based',
                'base_price' => 80,
                'unit' => 'kg',
            ])->assertStatus(201);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Tomatoes 2',
                'pricing_type' => 'weight_based',
                'base_price' => 80,
                'unit' => 'pcs',
            ])->assertStatus(422)->assertJsonValidationErrors(['unit']);
    }

    public function test_perishable_products_require_an_expiry_date(): void
    {
        [, $domain, $owner] = $this->makeStoreWithAdmin();

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Milk 1L',
                'pricing_type' => 'fixed',
                'base_price' => 250,
                'unit' => 'pcs',
                'is_perishable' => true,
            ])->assertStatus(422)->assertJsonValidationErrors(['expiry_date']);
    }

    public function test_creating_a_product_without_variants_auto_creates_one_inventory_row(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Sugar 1kg',
                'pricing_type' => 'fixed',
                'base_price' => 150,
                'unit' => 'pcs',
            ]);

        $productId = $response->json('data.id');

        $this->assertDatabaseHas('inventory', [
            'product_id' => $productId,
            'variant_id' => null,
            'store_id' => $store->id,
        ]);
    }

    public function test_creating_a_product_with_variants_creates_one_inventory_row_per_variant(): void
    {
        [, $domain, $owner] = $this->makeStoreWithAdmin();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Running Shoes',
                'pricing_type' => 'fixed',
                'base_price' => 3500,
                'unit' => 'pcs',
                'has_variants' => true,
                'variants' => [
                    ['variant_type' => 'size', 'variant_value' => '42', 'stock_qty' => 5],
                    ['variant_type' => 'size', 'variant_value' => '43', 'stock_qty' => 8],
                ],
            ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');

        // CurrentStore is cleared by SyncCurrentStoreWithTenancy after each request,
        // so inspecting tenant-scoped models afterward needs an explicit bypass.
        CurrentStore::bypass(function () use ($productId) {
            $this->assertCount(2, ProductVariant::query()->where('product_id', $productId)->get());
            $this->assertCount(2, Inventory::query()->where('product_id', $productId)->get());

            $variant = ProductVariant::where('product_id', $productId)->where('variant_value', '43')->first();
            $this->assertDatabaseHas('inventory', ['variant_id' => $variant->id, 'quantity_on_hand' => 8]);
        });
    }

    public function test_variants_require_a_variant_array_when_has_variants_is_true(): void
    {
        [, $domain, $owner] = $this->makeStoreWithAdmin();

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, 'products'), [
                'name' => 'Sandals',
                'pricing_type' => 'fixed',
                'base_price' => 1500,
                'unit' => 'pcs',
                'has_variants' => true,
            ])->assertStatus(422)->assertJsonValidationErrors(['variants']);
    }

    public function test_sku_is_unique_per_store_but_can_repeat_across_stores(): void
    {
        [, $domainA, $ownerA] = $this->makeStoreWithAdmin();
        [, $domainB, $ownerB] = $this->makeStoreWithAdmin();

        $payload = [
            'name' => 'Cooking Oil 1L',
            'sku' => 'OIL-001',
            'pricing_type' => 'fixed',
            'base_price' => 600,
            'unit' => 'pcs',
        ];

        $this->actingAs($ownerA, 'sanctum')->postJson($this->apiUrl($domainA, 'products'), $payload)->assertStatus(201);

        // Same SKU, same store -> rejected.
        $this->actingAs($ownerA, 'sanctum')->postJson($this->apiUrl($domainA, 'products'), $payload)
            ->assertStatus(422)->assertJsonValidationErrors(['sku']);

        // Same SKU, different store -> allowed.
        $this->actingAs($ownerB, 'sanctum')->postJson($this->apiUrl($domainB, 'products'), $payload)
            ->assertStatus(201);
    }

    public function test_a_stores_admin_cannot_touch_another_stores_products(): void
    {
        [$storeA, $domainA, $ownerA] = $this->makeStoreWithAdmin();
        [$storeB, $domainB, $ownerB] = $this->makeStoreWithAdmin();

        $productB = CurrentStore::bypass(fn () => Product::factory()->create(['store_id' => $storeB->id]));

        // Owner A's token, pointed at Store B's subdomain -> blocked before any query runs.
        $this->actingAs($ownerA, 'sanctum')
            ->getJson($this->apiUrl($domainB, "products/{$productB->id}"))
            ->assertStatus(403);

        // Owner A, on their own subdomain, cannot see Store B's product either.
        $this->actingAs($ownerA, 'sanctum')
            ->getJson($this->apiUrl($domainA, "products/{$productB->id}"))
            ->assertStatus(404);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        [, $domain] = $this->makeStoreWithAdmin();

        $this->getJson($this->apiUrl($domain, 'products'))->assertStatus(401);
    }

    public function test_customers_cannot_manage_the_catalog(): void
    {
        [$store, $domain] = $this->makeStoreWithAdmin();
        $customer = User::factory()->create(['store_id' => $store->id]);

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->apiUrl($domain, 'products'))
            ->assertStatus(403);
    }

    public function test_deleting_a_product_deactivates_it_instead_of_removing_it(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $product = CurrentStore::bypass(fn () => Product::factory()->create(['store_id' => $store->id]));

        $this->actingAs($owner, 'sanctum')
            ->deleteJson($this->apiUrl($domain, "products/{$product->id}"))
            ->assertStatus(200);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_inventory_can_be_manually_adjusted(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        [$product, $inventory] = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id]);
            $inventory = Inventory::factory()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

            return [$product, $inventory];
        });

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "inventory/{$inventory->id}"), [
                'quantity_on_hand' => 25,
                'reorder_level' => 5,
            ])->assertStatus(200);

        $this->assertDatabaseHas('inventory', ['id' => $inventory->id, 'quantity_on_hand' => 25, 'reorder_level' => 5]);
        $this->assertNotNull($inventory->fresh()->last_restocked_at);
    }

    public function test_adding_a_first_variant_removes_the_stale_base_inventory_row(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id, 'has_variants' => false]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'variant_id' => null]);

            return $product;
        });

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->apiUrl($domain, "products/{$product->id}/variants"), [
                'variant_type' => 'size',
                'variant_value' => 'M',
                'stock_qty' => 7,
            ])->assertStatus(201);

        CurrentStore::bypass(function () use ($product) {
            $this->assertDatabaseMissing('inventory', ['product_id' => $product->id, 'variant_id' => null]);
            $this->assertCount(1, Inventory::query()->where('product_id', $product->id)->get());
        });
    }

    public function test_low_stock_filter_only_returns_items_at_or_below_reorder_level(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        CurrentStore::bypass(function () use ($store) {
            $lowProduct = Product::factory()->create(['store_id' => $store->id]);
            Inventory::factory()->create(['store_id' => $store->id, 'product_id' => $lowProduct->id, 'quantity_on_hand' => 2, 'reorder_level' => 10]);

            $healthyProduct = Product::factory()->create(['store_id' => $store->id]);
            Inventory::factory()->create(['store_id' => $store->id, 'product_id' => $healthyProduct->id, 'quantity_on_hand' => 100, 'reorder_level' => 10]);
        });

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, 'inventory?low_stock=1'));

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_expiring_soon_filter_only_returns_perishables_near_expiry(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        CurrentStore::bypass(function () use ($store) {
            $soon = Product::factory()->perishable()->create([
                'store_id' => $store->id,
                'expiry_date' => now()->addDay()->toDateString(),
            ]);
            Inventory::factory()->create(['store_id' => $store->id, 'product_id' => $soon->id]);

            $later = Product::factory()->perishable()->create([
                'store_id' => $store->id,
                'expiry_date' => now()->addDays(30)->toDateString(),
            ]);
            Inventory::factory()->create(['store_id' => $store->id, 'product_id' => $later->id]);
        });

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, 'inventory?expiring_soon=1'));

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_flag_expired_products_command_deactivates_and_notifies_the_owner(): void
    {
        Notification::fake();

        $store = CurrentStore::bypass(fn () => Store::factory()->create());
        $owner = CurrentStore::bypass(fn () => Organization::find($store->organization_id)->owner);

        $expiredProduct = CurrentStore::bypass(fn () => Product::factory()->perishable()->create([
            'store_id' => $store->id,
            'expiry_date' => now()->subDay()->toDateString(),
        ]));

        $this->artisan('products:flag-expired')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $expiredProduct->id, 'is_active' => false]);
        Notification::assertSentTo($owner, ProductExpired::class);
    }
}
