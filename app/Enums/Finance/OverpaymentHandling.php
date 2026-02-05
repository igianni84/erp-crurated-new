<?php

namespace App\Enums\Finance;

/**
 * OverpaymentHandling Enum
 *
 * Defines how to handle overpayments when applying a payment to an invoice.
 */
enum OverpaymentHandling: string
{
    /**
     * Apply only the outstanding amount, leave remainder on payment.
     * The remaining amount stays as unapplied on the payment.
     */
    case ApplyPartial = 'apply_partial';

    /**
     * Apply full payment amount and create customer credit for the excess.
     * Customer can use the credit on future invoices.
     */
    case CreateCredit = 'create_credit';

    /**
     * Get the human-readable label for this option.
     */
    public function label(): string
    {
        return match ($this) {
            self::ApplyPartial => 'Apply Partial (Leave Remainder)',
            self::CreateCredit => 'Apply Full & Create Credit',
        };
    }

    /**
     * Get the description for this option.
     */
    public function description(): string
    {
        return match ($this) {
            self::ApplyPartial => 'Only apply the outstanding invoice amount. The remaining payment amount stays unapplied and can be applied to other invoices.',
            self::CreateCredit => 'Apply the full payment amount. The excess is converted to a customer credit that can be used on future invoices.',
        };
    }
}
