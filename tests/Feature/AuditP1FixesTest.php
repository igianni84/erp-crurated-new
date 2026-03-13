<?php

namespace Tests\Feature;

use App\Enums\Allocation\AllocationSourceType;
use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\AllocationSupplyForm;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use App\Policies\VoucherPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuditP1FixesTest extends TestCase
{
    use RefreshDatabase;

    private VoucherPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VoucherPolicy;
    }

    // =========================================================================
    // Helper: create a Voucher with all required dependencies
    // =========================================================================

    private function createVoucher(): Voucher
    {
        $wineMaster = WineMaster::create([
            'name' => 'Test Wine',
            'producer' => 'Test Producer',
            'country' => 'Italy',
        ]);

        $wineVariant = WineVariant::create([
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => 2020,
        ]);

        $format = Format::create([
            'name' => 'Standard 750ml',
            'volume_ml' => 750,
            'is_standard' => true,
        ]);

        $allocation = Allocation::create([
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'source_type' => AllocationSourceType::OwnedStock,
            'supply_form' => AllocationSupplyForm::Bottled,
            'total_quantity' => 100,
            'sold_quantity' => 0,
            'status' => AllocationStatus::Active,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'voucher-test@example.com',
        ]);

        return Voucher::create([
            'customer_id' => $customer->id,
            'allocation_id' => $allocation->id,
            'wine_variant_id' => $wineVariant->id,
            'format_id' => $format->id,
            'quantity' => 1,
            'lifecycle_state' => VoucherLifecycleState::Issued,
            'tradable' => true,
            'giftable' => false,
            'suspended' => false,
        ]);
    }

    // =========================================================================
    // 1. VoucherPolicy: manageFlags role-based access
    // =========================================================================

    public function test_viewer_cannot_manage_voucher_flags(): void
    {
        $viewer = User::factory()->viewer()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->manageFlags($viewer, $voucher));
    }

    public function test_editor_can_manage_voucher_flags(): void
    {
        $editor = User::factory()->editor()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->manageFlags($editor, $voucher));
    }

    // =========================================================================
    // 2. VoucherPolicy: update access
    // =========================================================================

    public function test_viewer_cannot_update_voucher(): void
    {
        $viewer = User::factory()->viewer()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->update($viewer, $voucher));
    }

    public function test_editor_can_update_voucher(): void
    {
        $editor = User::factory()->editor()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->update($editor, $voucher));
    }

    // =========================================================================
    // 3. VoucherPolicy: delete access
    // =========================================================================

    public function test_viewer_cannot_delete_voucher(): void
    {
        $viewer = User::factory()->viewer()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->delete($viewer, $voucher));
    }

    public function test_editor_cannot_delete_voucher(): void
    {
        $editor = User::factory()->editor()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->delete($editor, $voucher));
    }

    public function test_admin_can_delete_voucher(): void
    {
        $admin = User::factory()->admin()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->delete($admin, $voucher));
    }

    public function test_super_admin_can_delete_voucher(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->delete($superAdmin, $voucher));
    }

    // =========================================================================
    // 4. VoucherPolicy: transfer access
    // =========================================================================

    public function test_viewer_cannot_initiate_transfer(): void
    {
        $viewer = User::factory()->viewer()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->initiateTransfer($viewer, $voucher));
    }

    public function test_viewer_cannot_cancel_transfer(): void
    {
        $viewer = User::factory()->viewer()->create();
        $voucher = $this->createVoucher();

        $this->assertFalse($this->policy->cancelTransfer($viewer, $voucher));
    }

    public function test_editor_can_initiate_transfer(): void
    {
        $editor = User::factory()->editor()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->initiateTransfer($editor, $voucher));
    }

    public function test_editor_can_cancel_transfer(): void
    {
        $editor = User::factory()->editor()->create();
        $voucher = $this->createVoucher();

        $this->assertTrue($this->policy->cancelTransfer($editor, $voucher));
    }

    // =========================================================================
    // 5. Login rate limiter exists
    // =========================================================================

    public function test_login_rate_limiter_is_registered(): void
    {
        // The 'login' rate limiter should be registered in AppServiceProvider
        $this->assertTrue(
            RateLimiter::limiter('login') !== null,
            'The login rate limiter must be registered in the application.'
        );
    }

    // =========================================================================
    // 6. Composite index migration exists
    // =========================================================================

    public function test_composite_index_migration_file_exists(): void
    {
        $migrationPath = database_path('migrations/2026_03_13_133352_add_composite_indexes_for_soft_deleted_tables.php');

        $this->assertFileExists(
            $migrationPath,
            'The composite index migration file must exist.'
        );
    }
}
