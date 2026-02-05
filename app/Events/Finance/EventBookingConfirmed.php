<?php

namespace App\Events\Finance;

use App\Models\Customer\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: EventBookingConfirmed
 *
 * Dispatched when an event booking is confirmed.
 * Triggers auto-generation of INV4 (Service Events) invoice.
 *
 * This event is typically dispatched by:
 * - Event management system when a booking is confirmed
 * - Tasting event bookings
 * - Consultation bookings
 * - Other service event bookings
 *
 * Note: This event is defined in the Finance module as it represents
 * the financial consequence of an event booking. The event management
 * system should dispatch this event when a booking is confirmed and
 * payment is expected.
 *
 * INV4 expects immediate payment (no due date required).
 * INV4 can also be created manually for ad-hoc services (US-E049).
 */
class EventBookingConfirmed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Customer  $customer  The customer who made the booking
     * @param  string  $eventBookingId  Unique reference for the event booking
     * @param  array<int, array{description: string, quantity: int, unit_price: string, tax_rate: string, service_type?: string, pricing_snapshot_id?: string, metadata?: array<string, mixed>}>  $items  The service fees for this booking
     * @param  string  $currency  Currency code for the invoice (default: EUR)
     * @param  bool  $autoIssue  Whether to automatically issue the invoice after creation (default: true for immediate payment)
     * @param  array<string, mixed>|null  $metadata  Optional metadata about the booking (event details, venue, date, etc.)
     */
    public function __construct(
        public Customer $customer,
        public string $eventBookingId,
        public array $items,
        public string $currency = 'EUR',
        public bool $autoIssue = true,
        public ?array $metadata = null
    ) {}

    /**
     * Get the event name from metadata.
     */
    public function getEventName(): ?string
    {
        return $this->metadata['event_name'] ?? null;
    }

    /**
     * Get the event date from metadata.
     */
    public function getEventDate(): ?string
    {
        return $this->metadata['event_date'] ?? null;
    }

    /**
     * Get the event venue from metadata.
     */
    public function getEventVenue(): ?string
    {
        return $this->metadata['venue'] ?? null;
    }

    /**
     * Get the event type from metadata.
     */
    public function getEventType(): ?string
    {
        return $this->metadata['event_type'] ?? null;
    }

    /**
     * Get the number of attendees from metadata.
     */
    public function getAttendeeCount(): ?int
    {
        return $this->metadata['attendee_count'] ?? null;
    }

    /**
     * Check if this is a tasting event.
     */
    public function isTastingEvent(): bool
    {
        $eventType = $this->getEventType();

        return $eventType !== null && str_contains(strtolower($eventType), 'tasting');
    }

    /**
     * Check if this is a consultation.
     */
    public function isConsultation(): bool
    {
        $eventType = $this->getEventType();

        return $eventType !== null && str_contains(strtolower($eventType), 'consultation');
    }

    /**
     * Get the total amount for this booking (excluding tax).
     */
    public function getTotalAmount(): string
    {
        $total = '0.00';

        foreach ($this->items as $item) {
            $lineTotal = bcmul((string) $item['quantity'], $item['unit_price'], 2);
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }

    /**
     * Get the total tax amount for this booking.
     */
    public function getTotalTaxAmount(): string
    {
        $totalTax = '0.00';

        foreach ($this->items as $item) {
            $lineSubtotal = bcmul((string) $item['quantity'], $item['unit_price'], 2);
            $lineTax = bcmul($lineSubtotal, bcdiv($item['tax_rate'], '100', 6), 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);
        }

        return $totalTax;
    }

    /**
     * Get the service types present in this booking.
     *
     * @return array<int, string>
     */
    public function getServiceTypes(): array
    {
        $types = [];

        foreach ($this->items as $item) {
            if (isset($item['service_type']) && ! in_array($item['service_type'], $types, true)) {
                $types[] = $item['service_type'];
            }
        }

        return $types;
    }

    /**
     * Check if this booking has a specific service type.
     */
    public function hasServiceType(string $type): bool
    {
        foreach ($this->items as $item) {
            if (isset($item['service_type']) && $item['service_type'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the pricing snapshot ID for this booking.
     *
     * Returns the first available pricing snapshot ID from items or metadata.
     */
    public function getPricingSnapshotId(): ?string
    {
        // Check metadata first (batch-level snapshot)
        if ($this->metadata !== null && isset($this->metadata['pricing_snapshot_id'])) {
            return $this->metadata['pricing_snapshot_id'];
        }

        // Check items for individual snapshots
        foreach ($this->items as $item) {
            if (isset($item['pricing_snapshot_id'])) {
                return $item['pricing_snapshot_id'];
            }
        }

        return null;
    }
}
