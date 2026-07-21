<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAdminOrderManagementTest extends TestCase
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

    private function makeOrder(Store $store, array $attributes = []): Order
    {
        return CurrentStore::bypass(fn () => Order::factory()->create(['store_id' => $store->id] + $attributes));
    }

    // --- Status transitions ------------------------------------------------

    public function test_a_valid_transition_succeeds_and_logs_history(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $order = $this->makeOrder($store, ['status' => 'pending']);

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'confirmed']);

        $response->assertStatus(200)->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by' => $owner->id,
        ]);
    }

    public function test_skipping_a_step_is_rejected(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $order = $this->makeOrder($store, ['status' => 'pending']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'delivered'])
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_cancellation_is_allowed_from_any_non_terminal_state(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        foreach (['pending', 'confirmed', 'preparing', 'out_for_delivery'] as $state) {
            $order = $this->makeOrder($store, ['status' => $state]);

            $this->actingAs($owner, 'sanctum')
                ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'cancelled'])
                ->assertStatus(200);
        }
    }

    public function test_cannot_transition_out_of_a_terminal_state(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $delivered = $this->makeOrder($store, ['status' => 'delivered']);
        $cancelled = $this->makeOrder($store, ['status' => 'cancelled']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$delivered->id}/status"), ['status' => 'confirmed'])
            ->assertStatus(422);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$cancelled->id}/status"), ['status' => 'pending'])
            ->assertStatus(422);
    }

    public function test_a_cod_order_is_auto_marked_paid_on_delivery(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $order = $this->makeOrder($store, ['status' => 'out_for_delivery', 'payment_method' => 'cod', 'payment_status' => 'pending']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'delivered'])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_a_non_cod_order_is_not_auto_marked_paid_on_delivery(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $order = $this->makeOrder($store, ['status' => 'out_for_delivery', 'payment_method' => 'bank_transfer', 'payment_status' => 'pending']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'delivered'])
            ->assertStatus(200)
            ->assertJsonPath('data.payment_status', 'pending');
    }

    // --- Order detail --------------------------------------------------------

    public function test_order_detail_includes_customer_info_and_status_history(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Bilal Ahmed']);
        $order = $this->makeOrder($store, ['customer_id' => $customer->id, 'status' => 'pending']);

        $this->actingAs($owner, 'sanctum')
            ->patchJson($this->apiUrl($domain, "admin/orders/{$order->id}/status"), ['status' => 'confirmed']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, "admin/orders/{$order->id}"));

        $response->assertStatus(200)
            ->assertJsonPath('data.customer.name', 'Bilal Ahmed')
            ->assertJsonCount(1, 'data.status_histories');
    }

    // --- Dashboard -------------------------------------------------------------

    public function test_dashboard_returns_correct_metrics(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();

        $this->makeOrder($store, ['total_amount' => 100, 'status' => 'confirmed', 'created_at' => now()]);
        $this->makeOrder($store, ['total_amount' => 50, 'status' => 'cancelled', 'created_at' => now()]);
        $this->makeOrder($store, ['total_amount' => 200, 'status' => 'delivered', 'created_at' => now()->subDays(5)]);

        CurrentStore::bypass(function () use ($store) {
            $product = \App\Models\Product::factory()->create(['store_id' => $store->id]);
            \App\Models\Inventory::create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 2, 'reorder_level' => 10]);
        });

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, 'admin/dashboard'));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(2, $data['orders_today']); // excludes the 5-day-old order
        $this->assertEquals(100.0, (float) $data['revenue_today']); // excludes the cancelled one
        $this->assertEquals(300.0, (float) $data['revenue_to_date']); // excludes cancelled, includes the old delivered one
        $this->assertEquals(1, $data['low_stock_count']);
    }

    // --- Customers -------------------------------------------------------------

    public function test_customers_list_only_shows_customers_who_ordered_from_this_store(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $ordered = User::factory()->create(['role' => 'customer']);
        $neverOrdered = User::factory()->create(['role' => 'customer']);

        $this->makeOrder($store, ['customer_id' => $ordered->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, 'admin/customers'));

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame($ordered->id, $response->json('data.0.id'));
    }

    public function test_customer_detail_shows_their_order_history_for_this_store(): void
    {
        [$store, $domain, $owner] = $this->makeStoreWithAdmin();
        $customer = User::factory()->create(['role' => 'customer']);
        $this->makeOrder($store, ['customer_id' => $customer->id]);
        $this->makeOrder($store, ['customer_id' => $customer->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson($this->apiUrl($domain, "admin/customers/{$customer->id}"))
            ->assertStatus(200)
            ->assertJsonCount(2, 'orders.data');
    }

    // --- Isolation ---------------------------------------------------------

    public function test_a_stores_admin_cannot_see_another_stores_orders_dashboard_or_customers(): void
    {
        [$storeA, $domainA, $ownerA] = $this->makeStoreWithAdmin();
        [$storeB] = $this->makeStoreWithAdmin();

        $orderB = $this->makeOrder($storeB);

        $this->actingAs($ownerA, 'sanctum')
            ->getJson($this->apiUrl($domainA, "admin/orders/{$orderB->id}"))
            ->assertStatus(404);

        $this->actingAs($ownerA, 'sanctum')
            ->getJson($this->apiUrl($domainA, 'admin/orders'))
            ->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_customers_role_cannot_access_staff_admin_endpoints(): void
    {
        [$store, $domain] = $this->makeStoreWithAdmin();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->getJson($this->apiUrl($domain, 'admin/dashboard'))
            ->assertStatus(403);
    }
}
