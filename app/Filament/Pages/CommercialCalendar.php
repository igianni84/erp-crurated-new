<?php

namespace App\Filament\Pages;

use App\Enums\Commercial\ExecutionCadence;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PricingPolicyStatus;
use App\Filament\Resources\OfferResource;
use App\Filament\Resources\PriceBookResource;
use App\Filament\Resources\PricingPolicyResource;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PricingPolicy;
use Carbon\Carbon;
use Filament\Pages\Page;

class CommercialCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Commercial Calendar';

    protected static string $view = 'filament.pages.commercial-calendar';

    public string $calendarView = 'month';

    public ?string $eventTypeFilter = null;

    public ?string $channelFilter = null;

    public Carbon $currentDate;

    public function mount(): void
    {
        $this->currentDate = now();
    }

    /**
     * Navigate to previous period.
     */
    public function previousPeriod(): void
    {
        if ($this->calendarView === 'month') {
            $this->currentDate = $this->currentDate->copy()->subMonth();
        } else {
            $this->currentDate = $this->currentDate->copy()->subWeek();
        }
    }

    /**
     * Navigate to next period.
     */
    public function nextPeriod(): void
    {
        if ($this->calendarView === 'month') {
            $this->currentDate = $this->currentDate->copy()->addMonth();
        } else {
            $this->currentDate = $this->currentDate->copy()->addWeek();
        }
    }

    /**
     * Go to today.
     */
    public function goToToday(): void
    {
        $this->currentDate = now();
    }

    /**
     * Set calendar view mode.
     */
    public function setCalendarView(string $view): void
    {
        if (in_array($view, ['month', 'week'])) {
            $this->calendarView = $view;
        }
    }

    /**
     * Set event type filter.
     */
    public function setEventTypeFilter(?string $type): void
    {
        $this->eventTypeFilter = $type;
    }

    /**
     * Set channel filter.
     */
    public function setChannelFilter(?string $channelId): void
    {
        $this->channelFilter = $channelId;
    }

    /**
     * Get the date range for the current view.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getDateRange(): array
    {
        if ($this->calendarView === 'month') {
            $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
            $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();
        } else {
            $start = $this->currentDate->copy()->startOfWeek();
            $end = $this->currentDate->copy()->endOfWeek();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get all events for the current view period.
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     start: string,
     *     end: string|null,
     *     color: string,
     *     url: string,
     *     status: string,
     *     channel_id: string|null,
     *     channel_name: string|null
     * }>
     */
    public function getEvents(): array
    {
        $range = $this->getDateRange();
        $events = [];

        // Get Price Book events
        if ($this->eventTypeFilter === null || $this->eventTypeFilter === 'price_book') {
            $priceBooks = $this->getPriceBookEvents($range['start'], $range['end']);
            $events = array_merge($events, $priceBooks);
        }

        // Get Offer events
        if ($this->eventTypeFilter === null || $this->eventTypeFilter === 'offer') {
            $offers = $this->getOfferEvents($range['start'], $range['end']);
            $events = array_merge($events, $offers);
        }

        // Get Pricing Policy scheduled executions
        if ($this->eventTypeFilter === null || $this->eventTypeFilter === 'policy') {
            $policies = $this->getPolicyEvents($range['start'], $range['end']);
            $events = array_merge($events, $policies);
        }

        return $events;
    }

    /**
     * Get Price Book events for the date range.
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     start: string,
     *     end: string|null,
     *     color: string,
     *     url: string,
     *     status: string,
     *     channel_id: string|null,
     *     channel_name: string|null
     * }>
     */
    protected function getPriceBookEvents(Carbon $start, Carbon $end): array
    {
        $query = PriceBook::query()
            ->whereIn('status', [PriceBookStatus::Draft, PriceBookStatus::Active])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('valid_from', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('valid_from', '<=', $end)
                            ->where(function ($q3) use ($start) {
                                $q3->whereNull('valid_to')
                                    ->orWhere('valid_to', '>=', $start);
                            });
                    });
            });

        if ($this->channelFilter !== null) {
            $query->where('channel_id', $this->channelFilter);
        }

        $priceBooks = $query->with('channel')->get();

        return $priceBooks->map(function (PriceBook $priceBook) {
            return [
                'id' => 'pb_'.$priceBook->id,
                'type' => 'price_book',
                'title' => $priceBook->name,
                'start' => $priceBook->valid_from->format('Y-m-d'),
                'end' => $priceBook->valid_to?->format('Y-m-d'),
                'color' => $this->getPriceBookColor($priceBook->status),
                'url' => PriceBookResource::getUrl('view', ['record' => $priceBook]),
                'status' => $priceBook->status->value,
                'channel_id' => $priceBook->channel_id,
                'channel_name' => $priceBook->channel?->name,
            ];
        })->toArray();
    }

    /**
     * Get Offer events for the date range.
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     start: string,
     *     end: string|null,
     *     color: string,
     *     url: string,
     *     status: string,
     *     channel_id: string|null,
     *     channel_name: string|null
     * }>
     */
    protected function getOfferEvents(Carbon $start, Carbon $end): array
    {
        $query = Offer::query()
            ->whereIn('status', [OfferStatus::Draft, OfferStatus::Active, OfferStatus::Paused])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('valid_from', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('valid_from', '<=', $end)
                            ->where(function ($q3) use ($start) {
                                $q3->whereNull('valid_to')
                                    ->orWhere('valid_to', '>=', $start);
                            });
                    });
            });

        if ($this->channelFilter !== null) {
            $query->where('channel_id', $this->channelFilter);
        }

        $offers = $query->with(['channel', 'sellableSku'])->get();

        return $offers->map(function (Offer $offer) {
            $title = $offer->name;
            if ($offer->sellableSku !== null) {
                $title .= ' ('.$offer->sellableSku->sku_code.')';
            }

            return [
                'id' => 'of_'.$offer->id,
                'type' => 'offer',
                'title' => $title,
                'start' => $offer->valid_from->format('Y-m-d'),
                'end' => $offer->valid_to?->format('Y-m-d'),
                'color' => $this->getOfferColor($offer->status),
                'url' => OfferResource::getUrl('view', ['record' => $offer]),
                'status' => $offer->status->value,
                'channel_id' => $offer->channel_id,
                'channel_name' => $offer->channel?->name,
            ];
        })->toArray();
    }

    /**
     * Get Pricing Policy scheduled execution events for the date range.
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     start: string,
     *     end: string|null,
     *     color: string,
     *     url: string,
     *     status: string,
     *     channel_id: string|null,
     *     channel_name: string|null
     * }>
     */
    protected function getPolicyEvents(Carbon $start, Carbon $end): array
    {
        // Only show scheduled policies that are active
        $policies = PricingPolicy::query()
            ->where('status', PricingPolicyStatus::Active)
            ->where('execution_cadence', ExecutionCadence::Scheduled)
            ->get();

        $events = [];

        foreach ($policies as $policy) {
            // Get scheduling info from logic_definition
            $schedule = $policy->logic_definition['schedule'] ?? null;

            if ($schedule === null) {
                // Show as recurring event on every day of the range
                // For simplicity, show one event per week for scheduled policies
                $current = $start->copy();
                while ($current->lte($end)) {
                    // Show on Mondays by default if no specific schedule
                    if ($current->isMonday()) {
                        $events[] = [
                            'id' => 'pp_'.$policy->id.'_'.$current->format('Ymd'),
                            'type' => 'policy',
                            'title' => $policy->name.' (scheduled)',
                            'start' => $current->format('Y-m-d'),
                            'end' => null,
                            'color' => '#818cf8', // Indigo for policies
                            'url' => PricingPolicyResource::getUrl('view', ['record' => $policy]),
                            'status' => $policy->status->value,
                            'channel_id' => null,
                            'channel_name' => null,
                        ];
                    }
                    $current->addDay();
                }
            } else {
                // Parse schedule and add specific events
                $frequency = $schedule['frequency'] ?? 'weekly';
                $dayOfWeek = $schedule['day_of_week'] ?? 1; // 1 = Monday

                $current = $start->copy();
                while ($current->lte($end)) {
                    $shouldAdd = false;

                    if ($frequency === 'daily') {
                        $shouldAdd = true;
                    } elseif ($frequency === 'weekly' && $current->dayOfWeek === $dayOfWeek) {
                        $shouldAdd = true;
                    }

                    if ($shouldAdd) {
                        $events[] = [
                            'id' => 'pp_'.$policy->id.'_'.$current->format('Ymd'),
                            'type' => 'policy',
                            'title' => $policy->name.' (scheduled)',
                            'start' => $current->format('Y-m-d'),
                            'end' => null,
                            'color' => '#818cf8', // Indigo for policies
                            'url' => PricingPolicyResource::getUrl('view', ['record' => $policy]),
                            'status' => $policy->status->value,
                            'channel_id' => null,
                            'channel_name' => null,
                        ];
                    }

                    $current->addDay();
                }
            }
        }

        return $events;
    }

    /**
     * Get color for Price Book based on status.
     */
    protected function getPriceBookColor(PriceBookStatus $status): string
    {
        return match ($status) {
            PriceBookStatus::Draft => '#9ca3af', // Gray
            PriceBookStatus::Active => '#22c55e', // Green
            PriceBookStatus::Expired => '#f97316', // Orange
            PriceBookStatus::Archived => '#6b7280', // Gray-500
        };
    }

    /**
     * Get color for Offer based on status.
     */
    protected function getOfferColor(OfferStatus $status): string
    {
        return match ($status) {
            OfferStatus::Draft => '#9ca3af', // Gray
            OfferStatus::Active => '#3b82f6', // Blue
            OfferStatus::Paused => '#eab308', // Yellow
            OfferStatus::Expired => '#f97316', // Orange
            OfferStatus::Cancelled => '#ef4444', // Red
        };
    }

    /**
     * Get the days for the current view period.
     *
     * @return array<array{date: Carbon, isCurrentMonth: bool, isToday: bool, events: array}>
     */
    public function getCalendarDays(): array
    {
        $range = $this->getDateRange();
        $events = $this->getEvents();
        $days = [];

        $current = $range['start']->copy();
        $currentMonth = $this->currentDate->month;

        while ($current->lte($range['end'])) {
            $dateStr = $current->format('Y-m-d');

            // Find events for this day
            $dayEvents = array_filter($events, function ($event) use ($dateStr) {
                $eventStart = $event['start'];
                $eventEnd = $event['end'] ?? $eventStart;

                return $dateStr >= $eventStart && $dateStr <= $eventEnd;
            });

            $days[] = [
                'date' => $current->copy(),
                'isCurrentMonth' => $current->month === $currentMonth,
                'isToday' => $current->isToday(),
                'events' => array_values($dayEvents),
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Get available channels for filtering.
     *
     * @return array<string, string>
     */
    public function getChannelOptions(): array
    {
        return Channel::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get event type options for filtering.
     *
     * @return array<string, string>
     */
    public function getEventTypeOptions(): array
    {
        return [
            'price_book' => 'Price Books',
            'offer' => 'Offers',
            'policy' => 'Scheduled Policies',
        ];
    }

    /**
     * Get the current period label.
     */
    public function getCurrentPeriodLabel(): string
    {
        if ($this->calendarView === 'month') {
            return $this->currentDate->format('F Y');
        }

        $range = $this->getDateRange();

        return $range['start']->format('M d').' - '.$range['end']->format('M d, Y');
    }

    /**
     * Get the count of events by type.
     *
     * @return array{price_book: int, offer: int, policy: int}
     */
    public function getEventCounts(): array
    {
        $events = $this->getEvents();

        return [
            'price_book' => count(array_filter($events, fn ($e) => $e['type'] === 'price_book')),
            'offer' => count(array_filter($events, fn ($e) => $e['type'] === 'offer')),
            'policy' => count(array_filter($events, fn ($e) => $e['type'] === 'policy')),
        ];
    }
}
