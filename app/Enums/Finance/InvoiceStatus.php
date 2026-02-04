<?php

namespace App\Enums\Finance;

/**
 * Enum InvoiceStatus
 *
 * Lifecycle states for invoices.
 *
 * Allowed transitions:
 * - draft → issued, cancelled
 * - issued → paid, partially_paid, credited
 * - partially_paid → paid, credited
 * - paid, credited, cancelled → terminal (no transitions)
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Credited = 'credited';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially Paid',
            self::Credited => 'Credited',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'info',
            self::Paid => 'success',
            self::PartiallyPaid => 'warning',
            self::Credited => 'primary',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Issued => 'heroicon-o-document-text',
            self::Paid => 'heroicon-o-check-circle',
            self::PartiallyPaid => 'heroicon-o-clock',
            self::Credited => 'heroicon-o-receipt-refund',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::Paid, self::PartiallyPaid, self::Credited],
            self::PartiallyPaid => [self::Paid, self::Credited],
            self::Paid => [],
            self::Credited => [],
            self::Cancelled => [],
        };
    }

    /**
     * Check if transition to target status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Check if this is a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Credited, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * Check if this status allows editing of invoice details.
     * Only drafts can be edited.
     */
    public function allowsEditing(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if this status allows adding invoice lines.
     * Only drafts can have lines added.
     */
    public function allowsLineEditing(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if this status allows payments to be applied.
     */
    public function allowsPayment(): bool
    {
        return match ($this) {
            self::Issued, self::PartiallyPaid => true,
            default => false,
        };
    }

    /**
     * Check if this status allows credit notes to be created.
     */
    public function allowsCreditNote(): bool
    {
        return match ($this) {
            self::Issued, self::Paid, self::PartiallyPaid => true,
            default => false,
        };
    }

    /**
     * Check if this status allows cancellation.
     */
    public function allowsCancellation(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if invoice amounts are immutable in this status.
     * After issuance, amounts cannot be changed.
     */
    public function amountsImmutable(): bool
    {
        return $this !== self::Draft;
    }
}
