<?php

namespace Tests\Feature;

use App\Models\StoreRegistrationRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\StoreApprovalInvite;
use App\Notifications\StoreRegistrationRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SubscriptionPlan::create([
            'name' => 'Basic',
            'billing_cycle' => 'monthly',
            'features_json' => ['multi_store' => false],
        ]);
    }

    public function test_public_submission_creates_a_pending_request(): void
    {
        $response = $this->postJson('/api/store-registration-requests', [
            'business_name' => 'Ali Grocery',
            'category' => 'grocery',
            'owner_name' => 'Ali Khan',
            'owner_email' => 'ali@example.com',
            'owner_phone' => '+92-300-1234567',
            'address' => '123 Main St, Lahore',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('store_registration_requests', [
            'business_name' => 'Ali Grocery',
            'status' => 'pending',
        ]);
    }

    public function test_public_submission_validates_required_fields(): void
    {
        $response = $this->postJson('/api/store-registration-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['business_name', 'category', 'owner_name', 'owner_email', 'owner_phone', 'address']);
    }

    public function test_public_submission_is_rate_limited(): void
    {
        $payload = [
            'business_name' => 'Ali Grocery',
            'category' => 'grocery',
            'owner_name' => 'Ali Khan',
            'owner_email' => 'ali@example.com',
            'owner_phone' => '+92-300-1234567',
            'address' => '123 Main St, Lahore',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/store-registration-requests', $payload)->assertStatus(201);
        }

        $this->postJson('/api/store-registration-requests', $payload)->assertStatus(429);
    }

    public function test_guests_cannot_access_the_approval_queue(): void
    {
        $this->getJson('/api/admin/store-registration-requests')->assertStatus(401);
    }

    public function test_non_super_admins_cannot_access_the_approval_queue(): void
    {
        $orgOwner = User::factory()->orgOwner()->create();

        $this->actingAs($orgOwner, 'sanctum')
            ->getJson('/api/admin/store-registration-requests')
            ->assertStatus(403);
    }

    public function test_super_admin_can_list_pending_requests(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        StoreRegistrationRequest::factory()->count(3)->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/store-registration-requests')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_approving_a_request_creates_owner_organization_store_domain_and_subscription(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $registrationRequest = StoreRegistrationRequest::factory()->create([
            'business_name' => 'Ali Grocery',
            'owner_email' => 'ali@example.com',
        ]);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/admin/store-registration-requests/{$registrationRequest->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('store_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => 'approved',
            'reviewed_by' => $superAdmin->id,
        ]);

        $owner = User::where('email', 'ali@example.com')->first();
        $this->assertNotNull($owner);
        $this->assertSame('org_owner', $owner->role);
        $this->assertNotNull($owner->invite_token);

        $this->assertDatabaseHas('organizations', ['name' => 'Ali Grocery', 'owner_user_id' => $owner->id]);

        $store = \App\Models\Store::where('name', 'Ali Grocery')->first();
        $this->assertNotNull($store);
        $this->assertSame('approved', $store->status);
        $this->assertSame($owner->store_id, $store->id);

        $this->assertDatabaseHas('domains', ['domain' => $store->slug.'.shopkit.test', 'tenant_id' => $store->id]);

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $store->organization_id,
            'status' => 'trialing',
        ]);

        Notification::assertSentTo($owner, StoreApprovalInvite::class);
    }

    public function test_cannot_approve_an_already_reviewed_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $registrationRequest = StoreRegistrationRequest::factory()->approved()->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/admin/store-registration-requests/{$registrationRequest->id}/approve")
            ->assertStatus(422);
    }

    public function test_rejecting_a_request_requires_a_reason(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $registrationRequest = StoreRegistrationRequest::factory()->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/admin/store-registration-requests/{$registrationRequest->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_rejecting_a_request_notifies_the_owner_with_the_reason(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->superAdmin()->create();
        $registrationRequest = StoreRegistrationRequest::factory()->create([
            'owner_email' => 'ali@example.com',
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/admin/store-registration-requests/{$registrationRequest->id}/reject", [
                'reason' => 'Duplicate business already registered.',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('store_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Duplicate business already registered.',
        ]);

        Notification::assertSentOnDemand(StoreRegistrationRejected::class);
    }

    public function test_owner_can_accept_a_valid_invite_and_set_a_password(): void
    {
        $owner = User::factory()->orgOwner()->unverified()->create([
            'invite_token' => 'valid-token',
            'invite_expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/invite/valid-token', [
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['data', 'token']);

        $owner->refresh();
        $this->assertNull($owner->invite_token);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-secure-password', $owner->password));
    }

    public function test_expired_invite_token_is_rejected(): void
    {
        User::factory()->orgOwner()->create([
            'invite_token' => 'expired-token',
            'invite_expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/invite/expired-token', [
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ])->assertStatus(404);
    }
}
