<?php

namespace Tests\Feature\Filament\Commercial;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Filament\Resources\ChannelResource\Pages\CreateChannel;
use App\Filament\Resources\ChannelResource\Pages\EditChannel;
use App\Filament\Resources\ChannelResource\Pages\ListChannels;
use App\Filament\Resources\ChannelResource\Pages\ViewChannel;
use App\Models\Commercial\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class ChannelResourceTest extends TestCase
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

        Livewire::test(ListChannels::class)
            ->assertSuccessful();
    }

    public function test_list_shows_channels(): void
    {
        $this->actingAsSuperAdmin();

        $channels = Channel::factory()->count(3)->create(['allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(ListChannels::class)
            ->assertCanSeeTableRecords($channels);
    }

    public function test_list_can_filter_by_channel_type(): void
    {
        $this->actingAsSuperAdmin();

        $b2c = Channel::factory()->create(['channel_type' => ChannelType::B2c, 'allowed_commercial_models' => ['voucher_based']]);
        $b2b = Channel::factory()->b2b()->create(['allowed_commercial_models' => ['sell_through']]);

        Livewire::test(ListChannels::class)
            ->filterTable('channel_type', ChannelType::B2c->value)
            ->assertCanSeeTableRecords([$b2c])
            ->assertCanNotSeeTableRecords([$b2b]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $this->actingAsSuperAdmin();

        $active = Channel::factory()->create(['status' => ChannelStatus::Active, 'allowed_commercial_models' => ['voucher_based']]);
        $inactive = Channel::factory()->inactive()->create(['allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(ListChannels::class)
            ->filterTable('status', ChannelStatus::Active->value)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_list_can_search_by_name(): void
    {
        $this->actingAsSuperAdmin();

        $target = Channel::factory()->create(['name' => 'Unique Premium Channel', 'allowed_commercial_models' => ['voucher_based']]);
        $other = Channel::factory()->create(['name' => 'Another Sales Channel', 'allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(ListChannels::class)
            ->searchTable('Unique Premium')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ── Create Page ─────────────────────────────────────────────

    public function test_create_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateChannel::class)
            ->assertSuccessful();
    }

    public function test_can_create_channel(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateChannel::class)
            ->fillForm([
                'name' => 'Test B2C Channel',
                'channel_type' => ChannelType::B2c->value,
                'default_currency' => 'EUR',
                'status' => ChannelStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('channels', [
            'name' => 'Test B2C Channel',
            'channel_type' => ChannelType::B2c->value,
            'default_currency' => 'EUR',
            'status' => ChannelStatus::Active->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateChannel::class)
            ->fillForm([
                'name' => null,
                'channel_type' => null,
                'default_currency' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'channel_type' => 'required', 'default_currency' => 'required']);
    }

    // ── Edit Page ───────────────────────────────────────────────

    public function test_edit_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $channel = Channel::factory()->create(['allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(EditChannel::class, ['record' => $channel->id])
            ->assertSuccessful();
    }

    public function test_can_update_channel(): void
    {
        $this->actingAsSuperAdmin();

        $channel = Channel::factory()->create(['allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(EditChannel::class, ['record' => $channel->id])
            ->fillForm([
                'name' => 'Updated Channel Name',
                'channel_type' => ChannelType::B2b->value,
                'default_currency' => 'GBP',
                'status' => ChannelStatus::Inactive->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'name' => 'Updated Channel Name',
            'channel_type' => ChannelType::B2b->value,
            'default_currency' => 'GBP',
            'status' => ChannelStatus::Inactive->value,
        ]);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $channel = Channel::factory()->create(['allowed_commercial_models' => ['voucher_based']]);

        Livewire::test(ViewChannel::class, ['record' => $channel->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListChannels::class)
            ->assertSuccessful();
    }
}
