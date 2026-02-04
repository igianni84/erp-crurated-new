<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * InvoicePayment Model (Pivot)
 *
 * Tracks the application of payments to invoices. A single payment can be
 * split across multiple invoices, and an invoice can receive multiple payments.
 *
 * Constraints:
 * - sum(amount_applied) per invoice <= invoice.total_amount
 * - sum(amount_applied) per payment <= payment.amount
 *
 * @property int $id
 * @property string $invoice_id
 * @property string $payment_id
 * @property string $amount_applied
 * @property \Carbon\Carbon $applied_at
 * @property int|null $applied_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class InvoicePayment extends Model
{
    use HasFactory;

    protected $table = 'invoice_payments';

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'amount_applied',
        'applied_at',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // Validate constraints before creating
        static::creating(function (InvoicePayment $invoicePayment): void {
            $invoicePayment->validateAmountConstraints();
        });

        // Validate constraints before updating
        static::updating(function (InvoicePayment $invoicePayment): void {
            $invoicePayment->validateAmountConstraints();
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // =========================================================================
    // Validation Methods
    // =========================================================================

    /**
     * Validate that the amount_applied doesn't exceed invoice or payment limits.
     *
     * @throws InvalidArgumentException
     */
    protected function validateAmountConstraints(): void
    {
        $this->validateInvoiceAmountConstraint();
        $this->validatePaymentAmountConstraint();
    }

    /**
     * Validate that sum(amount_applied) per invoice <= invoice.total_amount.
     *
     * @throws InvalidArgumentException
     */
    protected function validateInvoiceAmountConstraint(): void
    {
        $invoice = $this->invoice;
        if ($invoice === null) {
            return;
        }

        // Get total already applied to this invoice (excluding this record if updating)
        $query = self::where('invoice_id', $this->invoice_id);
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }
        $existingApplied = $query->sum('amount_applied');

        // Calculate total after this application
        $totalAfterApplication = bcadd((string) $existingApplied, $this->amount_applied, 2);

        // Compare with invoice total
        if (bccomp($totalAfterApplication, $invoice->total_amount, 2) > 0) {
            $maxAllowed = bcsub($invoice->total_amount, (string) $existingApplied, 2);
            throw new InvalidArgumentException(
                "Amount applied ({$this->amount_applied}) would exceed invoice total. ".
                "Maximum allowed: {$maxAllowed}"
            );
        }
    }

    /**
     * Validate that sum(amount_applied) per payment <= payment.amount.
     *
     * @throws InvalidArgumentException
     */
    protected function validatePaymentAmountConstraint(): void
    {
        $payment = $this->payment;
        if ($payment === null) {
            return;
        }

        // Get total already applied from this payment (excluding this record if updating)
        $query = self::where('payment_id', $this->payment_id);
        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }
        $existingApplied = $query->sum('amount_applied');

        // Calculate total after this application
        $totalAfterApplication = bcadd((string) $existingApplied, $this->amount_applied, 2);

        // Compare with payment amount
        if (bccomp($totalAfterApplication, $payment->amount, 2) > 0) {
            $maxAllowed = bcsub($payment->amount, (string) $existingApplied, 2);
            throw new InvalidArgumentException(
                "Amount applied ({$this->amount_applied}) would exceed payment amount. ".
                "Maximum allowed: {$maxAllowed}"
            );
        }
    }

    // =========================================================================
    // Static Helper Methods
    // =========================================================================

    /**
     * Get the total amount applied to an invoice.
     */
    public static function getTotalAppliedToInvoice(string $invoiceId): string
    {
        $total = self::where('invoice_id', $invoiceId)->sum('amount_applied');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Get the total amount applied from a payment.
     */
    public static function getTotalAppliedFromPayment(string $paymentId): string
    {
        $total = self::where('payment_id', $paymentId)->sum('amount_applied');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Get the remaining amount that can be applied to an invoice.
     */
    public static function getRemainingForInvoice(Invoice $invoice): string
    {
        $totalApplied = self::getTotalAppliedToInvoice($invoice->id);

        return bcsub($invoice->total_amount, $totalApplied, 2);
    }

    /**
     * Get the remaining amount that can be applied from a payment.
     */
    public static function getRemainingFromPayment(Payment $payment): string
    {
        $totalApplied = self::getTotalAppliedFromPayment($payment->id);

        return bcsub($payment->amount, $totalApplied, 2);
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    /**
     * Get formatted amount applied with currency (from invoice).
     */
    public function getFormattedAmountApplied(): string
    {
        $invoice = $this->invoice;
        $currency = $invoice !== null ? $invoice->currency : 'EUR';

        return $currency.' '.number_format((float) $this->amount_applied, 2);
    }
}
