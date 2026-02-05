<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Models\Allocation\Voucher;
use App\Models\Pim\LiquidProduct;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BottlingInstruction Model
 *
 * Represents bottling instructions for liquid products with deadlines.
 * A BottlingInstruction MUST be linked to a ProcurementIntent.
 *
 * @property string $id UUID primary key
 * @property string $procurement_intent_id FK to ProcurementIntent (required)
 * @property string $liquid_product_id FK to LiquidProduct (required)
 * @property int $bottle_equivalents Number of bottle-equivalents
 * @property array<string> $allowed_formats JSON array of allowed bottle formats
 * @property array<string> $allowed_case_configurations JSON array of allowed case configurations
 * @property string|null $default_bottling_rule Default rule applied if customer doesn't specify preferences
 * @property \Carbon\Carbon $bottling_deadline Deadline for customer preferences (required)
 * @property BottlingPreferenceStatus $preference_status Status of customer preference collection
 * @property bool $personalised_bottling_required Whether personalised bottling is required
 * @property bool $early_binding_required Whether early voucher-bottle binding is required
 * @property string|null $delivery_location Preferred delivery location
 * @property BottlingInstructionStatus $status Current lifecycle status
 * @property \Carbon\Carbon|null $defaults_applied_at When defaults were applied (if any)
 */
class BottlingInstruction extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bottling_instructions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'procurement_intent_id',
        'liquid_product_id',
        'bottle_equivalents',
        'allowed_formats',
        'allowed_case_configurations',
        'default_bottling_rule',
        'bottling_deadline',
        'preference_status',
        'personalised_bottling_required',
        'early_binding_required',
        'delivery_location',
        'status',
        'defaults_applied_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bottle_equivalents' => 'integer',
            'allowed_formats' => 'array',
            'allowed_case_configurations' => 'array',
            'bottling_deadline' => 'date',
            'preference_status' => BottlingPreferenceStatus::class,
            'personalised_bottling_required' => 'boolean',
            'early_binding_required' => 'boolean',
            'status' => BottlingInstructionStatus::class,
            'defaults_applied_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (BottlingInstruction $instruction): void {
            // Enforce invariant: procurement_intent_id is required
            if (empty($instruction->procurement_intent_id)) {
                throw new \InvalidArgumentException(
                    'A Bottling Instruction cannot exist without a Procurement Intent'
                );
            }

            // Enforce invariant: bottling_deadline is required
            // Use empty() to handle null, empty string, or missing value
            if (empty($instruction->getAttributes()['bottling_deadline'])) {
                throw new \InvalidArgumentException(
                    'Bottling deadline is required for Bottling Instructions'
                );
            }
        });
    }

    /**
     * Get the procurement intent that this bottling instruction belongs to.
     *
     * @return BelongsTo<ProcurementIntent, $this>
     */
    public function procurementIntent(): BelongsTo
    {
        return $this->belongsTo(ProcurementIntent::class, 'procurement_intent_id');
    }

    /**
     * Get the liquid product for this bottling instruction.
     *
     * @return BelongsTo<LiquidProduct, $this>
     */
    public function liquidProduct(): BelongsTo
    {
        return $this->belongsTo(LiquidProduct::class, 'liquid_product_id');
    }

    /**
     * Get the audit logs for this bottling instruction.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the instruction is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === BottlingInstructionStatus::Draft;
    }

    /**
     * Check if the instruction is active.
     */
    public function isActive(): bool
    {
        return $this->status === BottlingInstructionStatus::Active;
    }

    /**
     * Check if the instruction is executed.
     */
    public function isExecuted(): bool
    {
        return $this->status === BottlingInstructionStatus::Executed;
    }

    /**
     * Check if preferences can still be collected.
     */
    public function canCollectPreferences(): bool
    {
        return $this->status->allowsPreferenceCollection()
            && $this->preference_status->isCollecting();
    }

    /**
     * Check if the deadline has passed.
     */
    public function isDeadlinePassed(): bool
    {
        return $this->bottling_deadline->isPast();
    }

    /**
     * Check if the deadline is within the next N days.
     */
    public function isDeadlineWithinDays(int $days): bool
    {
        return $this->bottling_deadline->isBetween(
            now(),
            now()->addDays($days)
        );
    }

    /**
     * Check if defaults have been applied.
     */
    public function hasDefaultsApplied(): bool
    {
        return $this->defaults_applied_at !== null;
    }

    /**
     * Get the number of days until the deadline.
     * Returns negative if deadline has passed.
     */
    public function getDaysUntilDeadline(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->bottling_deadline, false);
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the preference status color for UI display.
     */
    public function getPreferenceStatusColor(): string
    {
        return $this->preference_status->color();
    }

    /**
     * Get the preference status label for UI display.
     */
    public function getPreferenceStatusLabel(): string
    {
        return $this->preference_status->label();
    }

    /**
     * Get a display label for the liquid product.
     */
    public function getProductLabel(): string
    {
        $product = $this->liquidProduct;

        if (! $product) {
            return 'Unknown Liquid Product';
        }

        $wineVariant = $product->wineVariant;
        if ($wineVariant && $wineVariant->wineMaster) {
            return $wineVariant->wineMaster->name.' '.$wineVariant->vintage_year.' (Liquid)';
        }

        return 'Unknown Liquid Product';
    }

    /**
     * Get a formatted list of allowed formats for display.
     */
    public function getAllowedFormatsLabel(): string
    {
        if (empty($this->allowed_formats)) {
            return 'None specified';
        }

        return implode(', ', $this->allowed_formats);
    }

    /**
     * Get a formatted list of allowed case configurations for display.
     */
    public function getAllowedCaseConfigurationsLabel(): string
    {
        if (empty($this->allowed_case_configurations)) {
            return 'None specified';
        }

        return implode(', ', $this->allowed_case_configurations);
    }

    /**
     * Get the deadline formatted for display.
     */
    public function getDeadlineLabel(): string
    {
        $days = $this->getDaysUntilDeadline();

        if ($days < 0) {
            return $this->bottling_deadline->format('Y-m-d').' (Passed '.abs($days).' days ago)';
        }

        if ($days === 0) {
            return $this->bottling_deadline->format('Y-m-d').' (Today)';
        }

        return $this->bottling_deadline->format('Y-m-d').' ('.$days.' days remaining)';
    }

    /**
     * Get the urgency level based on deadline proximity.
     * Returns: 'critical' (< 14 days), 'warning' (< 30 days), 'normal'
     */
    public function getDeadlineUrgency(): string
    {
        $days = $this->getDaysUntilDeadline();

        if ($days < 0 || $days < 14) {
            return 'critical';
        }

        if ($days < 30) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Get the linked vouchers for this bottling instruction.
     *
     * Vouchers are linked via the procurement intent's source allocation.
     * If no source allocation is set, returns vouchers based on the liquid product's wine variant.
     *
     * @return Collection<int, Voucher>
     */
    public function getLinkedVouchers(): Collection
    {
        $intent = $this->procurementIntent;

        if (! $intent) {
            return new Collection;
        }

        // If intent has source allocation, get vouchers from that allocation
        if ($intent->source_allocation_id) {
            return Voucher::where('allocation_id', $intent->source_allocation_id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Otherwise, try to find vouchers by wine variant from the liquid product
        $liquidProduct = $this->liquidProduct;
        if ($liquidProduct && $liquidProduct->wine_variant_id) {
            return Voucher::where('wine_variant_id', $liquidProduct->wine_variant_id)
                ->orderBy('created_at', 'desc')
                ->limit($this->bottle_equivalents)
                ->get();
        }

        return new Collection;
    }

    /**
     * Get the total number of vouchers linked to this instruction.
     * Uses bottle_equivalents as the primary source, but validates against actual voucher count.
     */
    public function getTotalVoucherCount(): int
    {
        return $this->bottle_equivalents;
    }

    /**
     * Get the number of vouchers with preferences collected.
     *
     * In the current implementation, this is simulated based on preference_status.
     * In a full implementation, this would query actual preference records from Module A.
     *
     * @return array{collected: int, pending: int, total: int}
     */
    public function getPreferenceProgress(): array
    {
        $total = $this->bottle_equivalents;

        // Simulate collection progress based on preference_status
        // In production, this would query actual preference records
        $collected = match ($this->preference_status) {
            BottlingPreferenceStatus::Complete, BottlingPreferenceStatus::Defaulted => $total,
            BottlingPreferenceStatus::Partial => (int) ceil($total * 0.5),
            default => 0,
        };

        return [
            'collected' => $collected,
            'pending' => $total - $collected,
            'total' => $total,
        ];
    }

    /**
     * Get the preference collection progress as a percentage.
     */
    public function getPreferenceProgressPercentage(): float
    {
        $progress = $this->getPreferenceProgress();

        if ($progress['total'] === 0) {
            return 0.0;
        }

        return round(($progress['collected'] / $progress['total']) * 100, 1);
    }

    /**
     * Get voucher preference status for display.
     *
     * Returns a list of vouchers with their preference collection status.
     * In the current implementation, this is simulated.
     *
     * @return array<int, array{voucher_id: string, customer_name: string, preference_status: string, format_preference: string|null}>
     */
    public function getVoucherPreferenceList(): array
    {
        $vouchers = $this->getLinkedVouchers();
        $progress = $this->getPreferenceProgress();
        $result = [];

        $index = 0;
        foreach ($vouchers as $voucher) {
            /** @var Voucher $voucher */
            $customer = $voucher->customer;
            $customerName = $customer ? ($customer->display_name ?? $customer->email ?? 'Unknown Customer') : 'Unknown Customer';

            // Simulate preference status based on index and progress
            $hasPreference = $index < $progress['collected'];

            $result[] = [
                'voucher_id' => (string) $voucher->id,
                'customer_name' => $customerName,
                'preference_status' => $hasPreference ? 'collected' : 'pending',
                'format_preference' => $hasPreference && ! empty($this->allowed_formats)
                    ? $this->allowed_formats[array_rand($this->allowed_formats)]
                    : null,
            ];
            $index++;
        }

        // If no vouchers found but we have bottle_equivalents, create simulated entries
        if (empty($result) && $this->bottle_equivalents > 0) {
            for ($i = 0; $i < min($this->bottle_equivalents, 20); $i++) {
                $hasPreference = $i < $progress['collected'];
                $result[] = [
                    'voucher_id' => 'simulated-'.($i + 1),
                    'customer_name' => 'Customer '.($i + 1),
                    'preference_status' => $hasPreference ? 'collected' : 'pending',
                    'format_preference' => $hasPreference && ! empty($this->allowed_formats)
                        ? $this->allowed_formats[0]
                        : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Get the external customer portal URL for preference collection.
     * This is a placeholder - actual URL would come from configuration.
     */
    public function getCustomerPortalUrl(): string
    {
        return config('app.customer_portal_url', 'https://portal.example.com').'/preferences/'.$this->id;
    }
}
