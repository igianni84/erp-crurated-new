<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\Invoice;
use App\Models\User;
use App\Policies\Finance\InvoicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new InvoicePolicy;
    }

    public function test_all_users_can_view_any_invoices(): void
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

    public function test_all_users_can_view_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($this->policy->view($superAdmin, $invoice));
        $this->assertTrue($this->policy->view($admin, $invoice));
        $this->assertTrue($this->policy->view($editor, $invoice));
        $this->assertTrue($this->policy->view($viewer, $invoice));
    }

    public function test_editor_and_above_can_create_invoices(): void
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

    public function test_draft_invoice_can_be_updated(): void
    {
        $editor = User::factory()->editor()->create();
        $draftInvoice = Invoice::factory()->create();

        $this->assertTrue($this->policy->update($editor, $draftInvoice));
    }

    public function test_non_draft_invoice_cannot_be_updated(): void
    {
        $editor = User::factory()->editor()->create();
        $issuedInvoice = Invoice::factory()->issued()->create();

        $this->assertFalse($this->policy->update($editor, $issuedInvoice));
    }

    public function test_viewer_cannot_update_draft_invoice(): void
    {
        $viewer = User::factory()->viewer()->create();
        $draftInvoice = Invoice::factory()->create();

        $this->assertFalse($this->policy->update($viewer, $draftInvoice));
    }

    public function test_admin_can_delete_draft_invoice(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $draftInvoice = Invoice::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $draftInvoice));
        $this->assertTrue($this->policy->delete($superAdmin, $draftInvoice));
    }

    public function test_admin_cannot_delete_non_draft_invoice(): void
    {
        $admin = User::factory()->admin()->create();
        $issuedInvoice = Invoice::factory()->issued()->create();

        $this->assertFalse($this->policy->delete($admin, $issuedInvoice));
    }

    public function test_editor_cannot_delete_draft_invoice(): void
    {
        $editor = User::factory()->editor()->create();
        $draftInvoice = Invoice::factory()->create();

        $this->assertFalse($this->policy->delete($editor, $draftInvoice));
    }

    public function test_admin_and_above_can_restore_invoices(): void
    {
        $invoice = Invoice::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->restore($superAdmin, $invoice));
        $this->assertTrue($this->policy->restore($admin, $invoice));
    }

    public function test_editor_and_below_cannot_restore_invoices(): void
    {
        $invoice = Invoice::factory()->create();

        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->restore($editor, $invoice));
        $this->assertFalse($this->policy->restore($viewer, $invoice));
    }

    public function test_no_one_can_force_delete_invoices(): void
    {
        $invoice = Invoice::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->assertFalse($this->policy->forceDelete($superAdmin, $invoice));
        $this->assertFalse($this->policy->forceDelete($admin, $invoice));
        $this->assertFalse($this->policy->forceDelete($editor, $invoice));
        $this->assertFalse($this->policy->forceDelete($viewer, $invoice));
    }
}
