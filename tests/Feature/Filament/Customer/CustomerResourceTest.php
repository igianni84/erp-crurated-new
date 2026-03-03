<?php

namespace Tests\Feature\Filament\Customer;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Customer\CustomerType;
use App\Filament\Resources\Customer\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\Customer\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\Customer\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\Customer\CustomerResource\Pages\ViewCustomer;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page ───────────────────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListCustomers::class)
            ->assertSuccessful();
    }

    public function test_list_shows_customers(): void
    {
        $this->actingAsSuperAdmin();

        $customers = Customer::factory()->count(3)->create();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords($customers);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $prospect = Customer::factory()->prospect()->create();
        $active = Customer::factory()->active()->create();

        Livewire::test(ListCustomers::class)
            ->filterTable('status', CustomerStatus::Prospect->value)
            ->assertCanSeeTableRecords([$prospect])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_customer_type(): void
    {
        $this->actingAsSuperAdmin();

        $b2c = Customer::factory()->create(['customer_type' => CustomerType::B2C]);
        $b2b = Customer::factory()->create(['customer_type' => CustomerType::B2B]);

        Livewire::test(ListCustomers::class)
            ->filterTable('customer_type', CustomerType::B2C->value)
            ->assertCanSeeTableRecords([$b2c])
            ->assertCanNotSeeTableRecords([$b2b]);
    }

    public function test_list_can_search_by_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = Customer::factory()->create(['name' => 'Unique Wine Collector']);
        $other = Customer::factory()->create(['name' => 'Another Person']);

        Livewire::test(ListCustomers::class)
            ->searchTable('Unique Wine')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCustomer::class)
            ->assertSuccessful();
    }

    public function test_can_create_customer(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'customer_type' => CustomerType::B2C->value,
                'status' => CustomerStatus::Prospect->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'customer_type' => CustomerType::B2C->value,
            'status' => CustomerStatus::Prospect->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => null,
                'email' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'email' => 'required']);
    }

    public function test_create_validates_email_format(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Test',
                'email' => 'not-an-email',
                'customer_type' => CustomerType::B2C->value,
                'status' => CustomerStatus::Prospect->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_create_validates_unique_email(): void
    {
        $this->actingAsSuperAdmin();

        Customer::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Test',
                'email' => 'taken@example.com',
                'customer_type' => CustomerType::B2C->value,
                'status' => CustomerStatus::Prospect->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $customer = Customer::factory()->create();

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertSuccessful();
    }

    public function test_can_update_customer(): void
    {
        $this->actingAsSuperAdmin();

        $customer = Customer::factory()->create();

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->fillForm([
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'customer_type' => CustomerType::B2B->value,
                'status' => CustomerStatus::Prospect->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'customer_type' => CustomerType::B2B->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $customer = Customer::factory()->create();

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListCustomers::class)
            ->assertSuccessful();
    }

    public function test_viewer_cannot_create_customer(): void
    {
        $this->actingAsViewer();

        Livewire::test(CreateCustomer::class)
            ->assertForbidden();
    }

    public function test_viewer_cannot_edit_customer(): void
    {
        $this->actingAsViewer();

        $customer = Customer::factory()->create();

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertForbidden();
    }
}
