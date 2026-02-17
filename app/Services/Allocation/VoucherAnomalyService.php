<?php

namespace App\Services\Allocation;

use App\Models\Allocation\Voucher;
use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Service for managing anomalous vouchers and quarantine processes.
 *
 * Handles detection, quarantine, and resolution of vouchers with data integrity
 * issues or other anomalies that prevent normal operation.
 */
class VoucherAnomalyService
{
    /**
     * Common anomaly reasons.
     */
    public const REASON_MISSING_ALLOCATION = 'Missing or invalid allocation lineage';

    public const REASON_MISSING_CUSTOMER = 'Missing customer reference';

    public const REASON_MISSING_BOTTLE_SKU = 'Missing or incomplete bottle SKU reference';

    public const REASON_DATA_IMPORT_FAILURE = 'Data import validation failure';

    public const REASON_MANUAL_REVIEW = 'Flagged for manual review';

    /**
     * Mark a voucher as requiring attention (quarantine).
     *
     * Quarantined vouchers are excluded from normal operations and require
     * manual intervention to resolve.
     *
     * @param  string  $reason  The reason for quarantine
     *
     * @throws InvalidArgumentException If voucher is already quarantined
     */
    public function quarantine(Voucher $voucher, string $reason): Voucher
    {
        if ($voucher->requires_attention) {
            throw new InvalidArgumentException(
                "Voucher {$voucher->id} is already quarantined. Reason: {$voucher->attention_reason}"
            );
        }

        $voucher->requires_attention = true;
        $voucher->attention_reason = $reason;
        $voucher->save();

        $this->logQuarantineEvent($voucher, $reason);

        Log::warning('Voucher quarantined', [
            'voucher_id' => $voucher->id,
            'reason' => $reason,
            'user_id' => Auth::id(),
        ]);

        return $voucher;
    }

    /**
     * Remove a voucher from quarantine (unquarantine).
     *
     * Should only be called after anomalies have been resolved.
     *
     * @throws InvalidArgumentException If voucher is not quarantined or still has anomalies
     */
    public function unquarantine(Voucher $voucher): Voucher
    {
        if (! $voucher->requires_attention) {
            throw new InvalidArgumentException(
                "Voucher {$voucher->id} is not quarantined."
            );
        }

        // Verify no remaining anomalies before unquarantining
        $anomalies = $voucher->getDetectedAnomalies();
        $remainingAnomalies = array_filter($anomalies, fn (string $a) => $a !== $voucher->attention_reason);

        if (! empty($remainingAnomalies)) {
            throw new InvalidArgumentException(
                "Cannot unquarantine voucher {$voucher->id}: remaining anomalies detected - "
                .implode(', ', $remainingAnomalies)
            );
        }

        $oldReason = $voucher->attention_reason;

        $voucher->requires_attention = false;
        $voucher->attention_reason = null;
        $voucher->save();

        $this->logUnquarantineEvent($voucher, $oldReason);

        Log::info('Voucher unquarantined', [
            'voucher_id' => $voucher->id,
            'previous_reason' => $oldReason,
            'user_id' => Auth::id(),
        ]);

        return $voucher;
    }

    /**
     * Update the quarantine reason for an already quarantined voucher.
     *
     * @throws InvalidArgumentException If voucher is not quarantined
     */
    public function updateReason(Voucher $voucher, string $newReason): Voucher
    {
        if (! $voucher->requires_attention) {
            throw new InvalidArgumentException(
                "Voucher {$voucher->id} is not quarantined. Use quarantine() instead."
            );
        }

        $oldReason = $voucher->attention_reason;
        $voucher->attention_reason = $newReason;
        $voucher->save();

        $voucher->auditLogs()->create([
            'event' => AuditLog::EVENT_UPDATED,
            'old_values' => ['attention_reason' => $oldReason],
            'new_values' => ['attention_reason' => $newReason],
            'user_id' => Auth::id(),
        ]);

        return $voucher;
    }

    /**
     * Scan a voucher for anomalies and return a validation result.
     *
     * This is useful for data import validation - if anomalies are detected,
     * the import should be rejected or the voucher should be quarantined.
     *
     * @return array{valid: bool, anomalies: list<string>, should_quarantine: bool}
     */
    public function validateVoucher(Voucher $voucher): array
    {
        $anomalies = $voucher->getDetectedAnomalies();

        return [
            'valid' => empty($anomalies),
            'anomalies' => $anomalies,
            'should_quarantine' => ! empty($anomalies),
        ];
    }

    /**
     * Validate voucher data BEFORE creation (for data import scenarios).
     *
     * This should be called before attempting to create a voucher from external data.
     * Returns validation errors if the data would create an anomalous voucher.
     *
     * @param  array<string, mixed>  $data  The voucher data to validate
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateVoucherData(array $data): array
    {
        $errors = [];

        // Check required fields (database-level constraints)
        if (empty($data['allocation_id'])) {
            $errors[] = self::REASON_MISSING_ALLOCATION;
        }

        if (empty($data['customer_id'])) {
            $errors[] = self::REASON_MISSING_CUSTOMER;
        }

        if (empty($data['wine_variant_id']) || empty($data['format_id'])) {
            $errors[] = self::REASON_MISSING_BOTTLE_SKU;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get all quarantined vouchers.
     *
     * @return Collection<int, Voucher>
     */
    public function getQuarantinedVouchers(): Collection
    {
        return Voucher::query()
            ->where('requires_attention', true)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Get quarantined vouchers count.
     */
    public function getQuarantinedCount(): int
    {
        return Voucher::query()
            ->where('requires_attention', true)
            ->count();
    }

    /**
     * Get vouchers with auto-detected anomalies (even if not explicitly quarantined).
     *
     * This is a more comprehensive scan that detects data integrity issues
     * that might not have been explicitly flagged.
     *
     * @return Collection<int, Voucher>
     */
    public function scanForAnomalies(): Collection
    {
        // Get all vouchers and filter those with anomalies
        // In a large database, this should be optimized with raw queries
        return Voucher::query()
            ->where(function ($query) {
                $query->where('requires_attention', true)
                    ->orWhereNull('allocation_id')
                    ->orWhereNull('customer_id')
                    ->orWhereNull('wine_variant_id')
                    ->orWhereNull('format_id');
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Attempt to auto-quarantine a voucher if it has anomalies.
     *
     * Returns true if the voucher was quarantined, false if no anomalies detected.
     */
    public function autoQuarantineIfNeeded(Voucher $voucher): bool
    {
        if ($voucher->requires_attention) {
            // Already quarantined
            return false;
        }

        $validation = $this->validateVoucher($voucher);

        if (! $validation['valid']) {
            $reason = implode('; ', $validation['anomalies']);
            $this->quarantine($voucher, $reason);

            return true;
        }

        return false;
    }

    /**
     * Log a quarantine event to the audit log.
     */
    protected function logQuarantineEvent(Voucher $voucher, string $reason): void
    {
        $voucher->auditLogs()->create([
            'event' => AuditLog::EVENT_VOUCHER_QUARANTINED,
            'old_values' => [
                'requires_attention' => false,
                'attention_reason' => null,
            ],
            'new_values' => [
                'requires_attention' => true,
                'attention_reason' => $reason,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log an unquarantine event to the audit log.
     */
    protected function logUnquarantineEvent(Voucher $voucher, ?string $oldReason): void
    {
        $voucher->auditLogs()->create([
            'event' => AuditLog::EVENT_VOUCHER_UNQUARANTINED,
            'old_values' => [
                'requires_attention' => true,
                'attention_reason' => $oldReason,
            ],
            'new_values' => [
                'requires_attention' => false,
                'attention_reason' => null,
            ],
            'user_id' => Auth::id(),
        ]);
    }
}
