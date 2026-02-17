<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\ExecutionStatus;
use App\Enums\Commercial\ExecutionType;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PricingPolicyExecution Model
 *
 * Represents a single execution of a Pricing Policy.
 * Tracks execution results including processed SKUs, generated prices,
 * errors, and a log summary.
 *
 * Execution logs are immutable - they cannot be modified after creation.
 *
 * @property string $id
 * @property string $pricing_policy_id
 * @property Carbon $executed_at
 * @property ExecutionType $execution_type
 * @property int $skus_processed
 * @property int $prices_generated
 * @property int $errors_count
 * @property ExecutionStatus $status
 * @property string|null $log_summary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PricingPolicyExecution extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pricing_policy_executions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pricing_policy_id',
        'executed_at',
        'execution_type',
        'skus_processed',
        'prices_generated',
        'errors_count',
        'status',
        'log_summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'execution_type' => ExecutionType::class,
            'skus_processed' => 'integer',
            'prices_generated' => 'integer',
            'errors_count' => 'integer',
            'status' => ExecutionStatus::class,
        ];
    }

    /**
     * Get the pricing policy that was executed.
     *
     * @return BelongsTo<PricingPolicy, $this>
     */
    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }

    /**
     * Check if the execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === ExecutionStatus::Success;
    }

    /**
     * Check if the execution was partial (some failures).
     */
    public function isPartial(): bool
    {
        return $this->status === ExecutionStatus::Partial;
    }

    /**
     * Check if the execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === ExecutionStatus::Failed;
    }

    /**
     * Check if this was a dry run execution.
     */
    public function isDryRun(): bool
    {
        return $this->execution_type === ExecutionType::DryRun;
    }

    /**
     * Check if this was a manual execution.
     */
    public function isManual(): bool
    {
        return $this->execution_type === ExecutionType::Manual;
    }

    /**
     * Check if this was a scheduled execution.
     */
    public function isScheduled(): bool
    {
        return $this->execution_type === ExecutionType::Scheduled;
    }

    /**
     * Check if this execution had any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errors_count > 0;
    }

    /**
     * Get the success rate as a percentage.
     */
    public function getSuccessRate(): float
    {
        if ($this->skus_processed === 0) {
            return 0.0;
        }

        $successfulPrices = $this->prices_generated;
        $failedPrices = $this->errors_count;
        $totalAttempted = $successfulPrices + $failedPrices;

        if ($totalAttempted === 0) {
            return 0.0;
        }

        return round(($successfulPrices / $totalAttempted) * 100, 2);
    }

    /**
     * Get the execution type label for UI display.
     */
    public function getExecutionTypeLabel(): string
    {
        return $this->execution_type->label();
    }

    /**
     * Get the execution type color for UI display.
     */
    public function getExecutionTypeColor(): string
    {
        return $this->execution_type->color();
    }

    /**
     * Get the execution type icon for UI display.
     */
    public function getExecutionTypeIcon(): string
    {
        return $this->execution_type->icon();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get a summary of the execution for display.
     */
    public function getSummary(): string
    {
        $typeLabel = $this->execution_type->label();
        $statusLabel = $this->status->label();

        return "{$typeLabel} execution: {$statusLabel} - {$this->prices_generated} prices generated, {$this->errors_count} errors";
    }
}
