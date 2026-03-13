<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\Refund;
use App\Models\User;
use App\Policies\Finance\RefundPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RefundPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RefundPolicy;
    }

    public function test_all_users_can_view_any_refunds(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->viewAny($editor));
        $this->assertTrue($this->policy->viewAny($viewer));
    }

    public function test_all_users_can_view_refund(): void
    {
        $refund = Refund::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->view($superAdmin, $refund));
        $this->assertTrue($this->policy->view($admin, $refund));
        $this->assertTrue($this->policy->view($editor, $refund));
        $this->assertTrue($this->policy->view($viewer, $refund));
    }

    public function test_editor_and_above_can_create_refunds(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->create($editor));
        $this->assertFalse($this->policy->create($viewer));
    }

    public function test_pending_refund_can_be_updated(): void
    {
        $editor = User::factory()->editor()->create();
        $pendingRefund = Refund::factory()->create();

        $this->assertTrue($this->policy->update($editor, $pendingRefund));
    }

    public function test_non_pending_refund_cannot_be_updated(): void
    {
        $editor = User::factory()->editor()->create();
        $processedRefund = Refund::factory()->processed()->create();

        $this->assertFalse($this->policy->update($editor, $processedRefund));
    }

    public function test_no_one_can_delete_refunds(): void
    {
        $refund = Refund::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->delete($superAdmin, $refund));
        $this->assertFalse($this->policy->delete($admin, $refund));
        $this->assertFalse($this->policy->delete($editor, $refund));
        $this->assertFalse($this->policy->delete($viewer, $refund));
    }

    public function test_no_one_can_restore_refunds(): void
    {
        $refund = Refund::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->restore($superAdmin, $refund));
        $this->assertFalse($this->policy->restore($admin, $refund));
        $this->assertFalse($this->policy->restore($editor, $refund));
        $this->assertFalse($this->policy->restore($viewer, $refund));
    }

    public function test_no_one_can_force_delete_refunds(): void
    {
        $refund = Refund::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->forceDelete($superAdmin, $refund));
        $this->assertFalse($this->policy->forceDelete($admin, $refund));
        $this->assertFalse($this->policy->forceDelete($editor, $refund));
        $this->assertFalse($this->policy->forceDelete($viewer, $refund));
    }
}
