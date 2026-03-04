<?php

namespace Tests\Feature\Filament\Finance;

use App\Filament\Resources\Finance\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\Resources\Finance\SubscriptionResource\Pages\ViewSubscription;
use App\Models\Finance\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
    }

    // ── List Page (Read-Only) ───────────────────────────────────

    public function test_list_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ListSubscriptions::class)
            ->assertSuccessful();
    }

    public function test_list_shows_subscriptions(): void
    {
        $this->actingAsSuperAdmin();

        $subscriptions = Subscription::factory()->count(3)->create();

        Livewire::test(ListSubscriptions::class)
            ->assertCanSeeTableRecords($subscriptions);
    }

    // ── View Page ───────────────────────────────────────────────

    public function test_view_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $subscription = Subscription::factory()->create();

        Livewire::test(ViewSubscription::class, ['record' => $subscription->id])
            ->assertSuccessful();
    }

    // ── Authorization ───────────────────────────────────────────

    public function test_viewer_can_access_list(): void
    {
        $this->actingAsViewer();

        Livewire::test(ListSubscriptions::class)
            ->assertSuccessful();
    }
}
