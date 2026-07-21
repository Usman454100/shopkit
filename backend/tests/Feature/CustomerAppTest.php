<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentStore::clear();
        parent::tearDown();
    }

    private function makeStore(array $attributes = []): Store
    {
        return CurrentStore::bypass(function () use ($attributes) {
            $store = Store::factory()->create($attributes);
            $store->createDomain($store->slug.'.'.config('tenancy.central_domain'));

            return $store;
        });
    }

    private function apiUrl(string $domain, string $path): string
    {
        return "http://{$domain}/api/{$path}";
    }

    // --- Registration & directory --------------------------------------------

    public function test_a_customer_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ayesha Noor',
            'email' => 'ayesha@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['data', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'ayesha@example.com', 'role' => 'customer']);
    }

    public function test_store_lookup_returns_an_approved_store_by_slug(): void
    {
        $store = $this->makeStore(['status' => 'approved']);

        $this->getJson("/api/stores/lookup?slug={$store->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', $store->slug);
    }

    public function test_store_lookup_hides_unapproved_stores(): void
    {
        $store = $this->makeStore(['status' => 'pending']);

        $this->getJson("/api/stores/lookup?slug={$store->slug}")->assertStatus(404);
    }

    public function test_store_search_filters_by_name(): void
    {
        $this->makeStore(['name' => 'Ali Grocery Mart', 'status' => 'approved']);
        $this->makeStore(['name' => 'Zainab Shoes', 'status' => 'approved']);

        $response = $this->getJson('/api/stores/search?query=Grocery');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_a_customer_can_join_a_store_and_joining_twice_is_idempotent(): void
    {
        $store = $this->makeStore();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/stores/{$store->id}/join")
            ->assertStatus(201);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/stores/{$store->id}/join")
            ->assertStatus(201);

        $this->assertDatabaseCount('customer_store_joins', 1);
    }

    // --- Catalog browsing (anonymous) -----------------------------------------

    public function test_anonymous_users_can_browse_the_catalog(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);
        });

        $this->getJson($this->apiUrl($domain, 'catalog/products'))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_catalog_excludes_inactive_products(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        CurrentStore::bypass(fn () => Product::factory()->create(['store_id' => $store->id, 'is_active' => false]));

        $this->getJson($this->apiUrl($domain, 'catalog/products'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_catalog_excludes_expired_perishables(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        CurrentStore::bypass(fn () => Product::factory()->perishable()->create([
            'store_id' => $store->id,
            'expiry_date' => now()->subDay()->toDateString(),
        ]));

        $this->getJson($this->apiUrl($domain, 'catalog/products'))
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_catalog_exposes_in_stock_flag_not_exact_quantity(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 37]);

            return $product;
        });

        $response = $this->getJson($this->apiUrl($domain, "catalog/products/{$product->id}"));

        $response->assertStatus(200)->assertJsonPath('data.in_stock', true);
        $response->assertJsonMissingPath('data.quantity_on_hand');
    }

    public function test_a_suspended_store_blocks_browsing(): void
    {
        $store = $this->makeStore(['status' => 'suspended']);
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        $this->getJson($this->apiUrl($domain, 'catalog/products'))->assertStatus(503);
    }

    // --- Checkout --------------------------------------------------------------

    private function makeCustomer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    public function test_checkout_requires_authentication(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');

        $this->postJson($this->apiUrl($domain, 'orders'), [])->assertStatus(401);
    }

    public function test_checkout_creates_an_order_decrements_stock_and_snapshots_pricing(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        [$product, $inventory] = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id, 'base_price' => 100]);
            $inventory = Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

            return [$product, $inventory];
        });

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $response->assertStatus(201)->assertJsonPath('data.total_amount', '300.00');

        CurrentStore::bypass(function () use ($product, $inventory) {
            $this->assertEquals(7, $inventory->fresh()->quantity_on_hand);
            $this->assertDatabaseHas('order_items', [
                'product_id' => $product->id,
                'quantity' => '3.00',
                'unit_price' => '100.00',
                'subtotal' => '300.00',
            ]);
        });
    }

    public function test_checkout_rejects_a_client_supplied_total_and_computes_its_own(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id, 'base_price' => 50]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

            return $product;
        });

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'total_amount' => 1, // attempted tampering — must be ignored
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ]);

        $response->assertStatus(201)->assertJsonPath('data.total_amount', '100.00');
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 2]);

            return $product;
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_checkout_rejects_an_expired_product_even_if_still_flagged_active(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->perishable()->create([
                'store_id' => $store->id,
                'expiry_date' => now()->subDay()->toDateString(),
                'is_active' => true, // flag-expired job hasn't run yet today
            ]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

            return $product;
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_checkout_rejects_non_cod_payment_methods(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $product = CurrentStore::bypass(function () use ($store) {
            $product = Product::factory()->create(['store_id' => $store->id]);
            Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

            return $product;
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'jazzcash',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertStatus(422)->assertJsonValidationErrors(['payment_method']);
    }

    public function test_checkout_requires_variant_selection_when_product_has_variants(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $product = CurrentStore::bypass(fn () => Product::factory()->withVariants()->create(['store_id' => $store->id]));

        $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domain, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_a_customer_can_view_their_own_order_history_and_detail_but_not_others(): void
    {
        $store = $this->makeStore();
        $domain = $store->slug.'.'.config('tenancy.central_domain');
        $customerA = $this->makeCustomer();
        $customerB = $this->makeCustomer();

        $orderA = CurrentStore::bypass(fn () => Order::factory()->create(['store_id' => $store->id, 'customer_id' => $customerA->id]));

        $this->actingAs($customerA, 'sanctum')
            ->getJson($this->apiUrl($domain, 'orders'))
            ->assertStatus(200)->assertJsonCount(1, 'data');

        $this->actingAs($customerA, 'sanctum')
            ->getJson($this->apiUrl($domain, "orders/{$orderA->id}"))
            ->assertStatus(200);

        $this->actingAs($customerB, 'sanctum')
            ->getJson($this->apiUrl($domain, "orders/{$orderA->id}"))
            ->assertStatus(404);
    }

    public function test_a_customer_token_works_across_multiple_stores_subdomains(): void
    {
        $storeA = $this->makeStore();
        $storeB = $this->makeStore();
        $domainB = $storeB->slug.'.'.config('tenancy.central_domain');
        $customer = $this->makeCustomer();

        $productB = CurrentStore::bypass(function () use ($storeB) {
            $product = Product::factory()->create(['store_id' => $storeB->id]);
            Inventory::create(['store_id' => $storeB->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);

            return $product;
        });

        // Same customer, never "joined" Store B, still allowed to shop there —
        // joining is a convenience list, not an authorization gate (decision #5).
        $this->actingAs($customer, 'sanctum')
            ->postJson($this->apiUrl($domainB, 'orders'), [
                'delivery_address' => '123 Test Street',
                'payment_method' => 'cod',
                'items' => [['product_id' => $productB->id, 'quantity' => 1]],
            ])->assertStatus(201);
    }
}
