<?php

namespace App\Listeners\Finance;

use App\Enums\Finance\InvoiceType;
use App\Events\Finance\EventBookingConfirmed;
use App\Services\Finance\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listener: GenerateEventServiceInvoice
 *
 * Generates an INV4 (Service Events) invoice when an event booking is confirmed.
 *
 * This listener:
 * - Creates an invoice with type INV4
 * - Links the invoice to the event booking (source_type='event_booking')
 * - Creates invoice lines for event fees and service fees
 * - Sets status to issued immediately (INV4 expects immediate payment)
 *
 * Service types supported:
 * - event_attendance: Event attendance fees
 * - tasting_fee: Wine tasting service fees
 * - consultation: Consultation service fees
 * - other_service: Other service fees
 */
class GenerateEventServiceInvoice implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EventBookingConfirmed $event): void
    {
        $customer = $event->customer;

        // Check for existing invoice (idempotency)
        $existingInvoice = $this->invoiceService->findBySource('event_booking', $event->eventBookingId);
        if ($existingInvoice !== null) {
            Log::channel('finance')->info('Invoice already exists for event booking', [
                'event_booking_id' => $event->eventBookingId,
                'invoice_id' => $existingInvoice->id,
            ]);

            return;
        }

        // Validate items
        if (empty($event->items)) {
            Log::channel('finance')->error('Cannot generate INV4: no items in event booking', [
                'event_booking_id' => $event->eventBookingId,
                'customer_id' => $customer->id,
            ]);

            return;
        }

        // Build invoice lines from event booking items
        $lines = $this->buildInvoiceLines($event);

        // Create the draft invoice
        // Note: INV4 does not require a due date (immediate payment expected)
        $invoice = $this->invoiceService->createDraft(
            invoiceType: InvoiceType::ServiceEvents,
            customer: $customer,
            lines: $lines,
            sourceType: 'event_booking',
            sourceId: $event->eventBookingId,
            currency: $event->currency,
            dueDate: null, // INV4 expects immediate payment
            notes: $this->buildInvoiceNotes($event)
        );

        Log::channel('finance')->info('Generated INV4 draft invoice for event booking', [
            'event_booking_id' => $event->eventBookingId,
            'invoice_id' => $invoice->id,
            'total_amount' => $invoice->total_amount,
            'items_count' => count($event->items),
            'service_types' => $event->getServiceTypes(),
        ]);

        // Auto-issue immediately (INV4 is typically issued right away)
        if ($event->autoIssue) {
            try {
                $this->invoiceService->issue($invoice);
                Log::channel('finance')->info('Auto-issued INV4 invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'event_booking_id' => $event->eventBookingId,
                ]);
            } catch (Throwable $e) {
                Log::channel('finance')->error('Failed to auto-issue INV4 invoice', [
                    'invoice_id' => $invoice->id,
                    'event_booking_id' => $event->eventBookingId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build invoice lines from event booking items.
     *
     * @return array<int, array{description: string, quantity: string, unit_price: string, tax_rate: string, sellable_sku_id: int|null, metadata: array<string, mixed>}>
     */
    protected function buildInvoiceLines(EventBookingConfirmed $event): array
    {
        $lines = [];

        foreach ($event->items as $item) {
            // Build metadata with service information
            $metadata = [
                'event_booking_id' => $event->eventBookingId,
                'original_quantity' => $item['quantity'],
            ];

            // Add service type if provided
            if (isset($item['service_type'])) {
                $metadata['service_type'] = $item['service_type'];
            }

            // Add pricing snapshot ID if provided
            if (isset($item['pricing_snapshot_id'])) {
                $metadata['pricing_snapshot_id'] = $item['pricing_snapshot_id'];
            }

            // Add item-level metadata if provided
            if (isset($item['metadata'])) {
                $metadata['item_metadata'] = $item['metadata'];
            }

            // Add event metadata for context
            if ($event->getEventName() !== null) {
                $metadata['event_name'] = $event->getEventName();
            }
            if ($event->getEventDate() !== null) {
                $metadata['event_date'] = $event->getEventDate();
            }
            if ($event->getEventType() !== null) {
                $metadata['event_type'] = $event->getEventType();
            }

            $lines[] = [
                'description' => $item['description'],
                'quantity' => (string) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'sellable_sku_id' => null, // INV4 service fees are not linked to sellable_sku
                'metadata' => $metadata,
            ];
        }

        return $lines;
    }

    /**
     * Build notes for the invoice.
     */
    protected function buildInvoiceNotes(EventBookingConfirmed $event): string
    {
        $parts = [];

        // Add event name if available
        if ($event->getEventName() !== null) {
            $parts[] = $event->getEventName();
        }

        // Add event type and date
        $eventType = $event->getEventType();
        $eventDate = $event->getEventDate();

        if ($eventType !== null && $eventDate !== null) {
            $parts[] = ucfirst($eventType).' on '.$eventDate;
        } elseif ($eventType !== null) {
            $parts[] = ucfirst($eventType);
        } elseif ($eventDate !== null) {
            $parts[] = 'Event on '.$eventDate;
        }

        // Add venue if available
        if ($event->getEventVenue() !== null) {
            $parts[] = 'Venue: '.$event->getEventVenue();
        }

        // Add attendee count if available
        if ($event->getAttendeeCount() !== null) {
            $parts[] = $event->getAttendeeCount().' attendee(s)';
        }

        // Add booking reference
        $parts[] = 'Booking: '.$event->eventBookingId;

        // Add service types summary
        $serviceTypes = $event->getServiceTypes();
        if (! empty($serviceTypes)) {
            $parts[] = 'Services: '.implode(', ', array_map(fn ($t) => ucfirst(str_replace('_', ' ', $t)), $serviceTypes));
        }

        return implode('. ', $parts).'.';
    }
}
