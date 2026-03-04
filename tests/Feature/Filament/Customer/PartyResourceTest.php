<?php

namespace Tests\Feature\Filament\Customer;

use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Filament\Resources\Customer\PartyResource\Pages\CreateParty;
use App\Filament\Resources\Customer\PartyResource\Pages\EditParty;
use App\Filament\Resources\Customer\PartyResource\Pages\ListParties;
use App\Filament\Resources\Customer\PartyResource\Pages\ViewParty;
use App\Models\Customer\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PartyResourceTest extends TestCase
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

        Livewire::test(ListParties::class)
            ->assertSuccessful();
    }

    public function test_list_shows_parties(): void
    {
        $this->actingAsSuperAdmin();

        $parties = Party::factory()->count(3)->create();

        Livewire::test(ListParties::class)
            ->assertCanSeeTableRecords($parties);
    }

    public function test_list_can_filter_by_party_type(): void
    {
        $this->actingAsSuperAdmin();

        $individual = Party::factory()->individual()->create();
        $legalEntity = Party::factory()->legalEntity()->create();

        Livewire::test(ListParties::class)
            ->filterTable('party_type', PartyType::Individual->value)
            ->assertCanSeeTableRecords([$individual])
            ->assertCanNotSeeTableRecords([$legalEntity]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = Party::factory()->create(['status' => PartyStatus::Active]);
        $inactive = Party::factory()->create(['status' => PartyStatus::Inactive]);

        Livewire::test(ListParties::class)
            ->filterTable('status', PartyStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_list_can_search_by_legal_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = Party::factory()->create(['legal_name' => 'Unique Estate Winery']);
        $other = Party::factory()->create(['legal_name' => 'Another Company']);

        Livewire::test(ListParties::class)
            ->searchTable('Unique Estate')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateParty::class)
            ->assertSuccessful();
    }

    public function test_can_create_party(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateParty::class)
            ->fillForm([
                'legal_name' => 'Test Legal Entity',
                'party_type' => PartyType::LegalEntity->value,
                'status' => PartyStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('parties', [
            'legal_name' => 'Test Legal Entity',
            'party_type' => PartyType::LegalEntity->value,
            'status' => PartyStatus::Active->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateParty::class)
            ->fillForm([
                'legal_name' => null,
                'party_type' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['legal_name' => 'required', 'party_type' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $party = Party::factory()->create();

        Livewire::test(EditParty::class, ['record' => $party->id])
            ->assertSuccessful();
    }

    public function test_can_update_party(): void
    {
        $this->actingAsSuperAdmin();

        $party = Party::factory()->legalEntity()->create();

        Livewire::test(EditParty::class, ['record' => $party->id])
            ->fillForm([
                'legal_name' => 'Updated Name',
                'party_type' => PartyType::Individual->value,
                'status' => PartyStatus::Inactive->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('parties', [
            'id' => $party->id,
            'legal_name' => 'Updated Name',
            'party_type' => PartyType::Individual->value,
            'status' => PartyStatus::Inactive->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $party = Party::factory()->create();

        Livewire::test(ViewParty::class, ['record' => $party->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListParties::class)
            ->assertSuccessful();
    }
}
