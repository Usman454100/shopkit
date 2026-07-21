<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\Tenancy\CurrentStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestWidget;
use Tests\TestCase;

/**
 * The Milestone 0 gate (docs/07-ROADMAP.md): this must pass before any
 * tenant-scoped feature work begins. Proves BelongsToStore's global scope
 * actually prevents Store A from ever reading or writing Store B's rows,
 * and that it fails closed when no store context is set at all.
 */
class StoreIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Store $storeA;

    private Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id')->index();
            $table->string('name');
            $table->timestamps();
        });

        $this->storeA = $this->makeStore('Store A');
        $this->storeB = $this->makeStore('Store B');

        CurrentStore::clear();
    }

    protected function tearDown(): void
    {
        CurrentStore::clear();
        Schema::dropIfExists('test_widgets');

        parent::tearDown();
    }

    private function makeStore(string $name): Store
    {
        $owner = User::create([
            'name' => $name.' Owner',
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => 'password',
            'role' => 'org_owner',
        ]);

        $organization = Organization::create([
            'name' => $name.' Org',
            'owner_user_id' => $owner->id,
            'isolation_tier_default' => 'shared',
        ]);

        return Store::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => str_replace(' ', '-', strtolower($name)),
            'category' => 'grocery',
            'status' => 'approved',
            'isolation_tier' => 'shared',
        ]);
    }

    public function test_creating_a_record_auto_assigns_the_current_store(): void
    {
        CurrentStore::set($this->storeA->id);

        $widget = TestWidget::create(['name' => 'Widget from A']);

        $this->assertSame($this->storeA->id, $widget->store_id);
    }

    public function test_store_a_cannot_see_store_bs_rows(): void
    {
        CurrentStore::set($this->storeA->id);
        TestWidget::create(['name' => 'A-1']);
        TestWidget::create(['name' => 'A-2']);

        CurrentStore::set($this->storeB->id);
        $bWidget = TestWidget::create(['name' => 'B-1']);

        CurrentStore::set($this->storeA->id);
        $visible = TestWidget::all();

        $this->assertCount(2, $visible);
        $this->assertTrue($visible->every(fn (TestWidget $w) => $w->store_id === $this->storeA->id));
        $this->assertFalse($visible->contains('id', $bWidget->id));
    }

    public function test_store_a_cannot_fetch_store_bs_row_by_id_directly(): void
    {
        CurrentStore::set($this->storeB->id);
        $bWidget = TestWidget::create(['name' => 'B-only']);

        CurrentStore::set($this->storeA->id);

        $this->assertNull(TestWidget::find($bWidget->id));
    }

    public function test_query_fails_closed_with_no_store_context(): void
    {
        CurrentStore::set($this->storeA->id);
        TestWidget::create(['name' => 'A-1']);

        CurrentStore::set($this->storeB->id);
        TestWidget::create(['name' => 'B-1']);

        // No store context set at all — must return nothing, not everything.
        CurrentStore::clear();

        $this->assertCount(0, TestWidget::all());
    }

    public function test_bypass_is_the_only_sanctioned_way_to_see_across_stores(): void
    {
        CurrentStore::set($this->storeA->id);
        TestWidget::create(['name' => 'A-1']);

        CurrentStore::set($this->storeB->id);
        TestWidget::create(['name' => 'B-1']);

        CurrentStore::clear();

        $all = CurrentStore::bypass(fn () => TestWidget::all());

        $this->assertCount(2, $all);
    }
}
