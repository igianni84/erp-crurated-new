<?php

namespace App\Services\Commercial;

use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PriceSource;
use App\Models\AuditLog;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\SellableSku;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing PriceBook lifecycle and operations.
 *
 * Centralizes all PriceBook business logic including state transitions,
 * cloning, and price resolution operations.
 */
class PriceBookService
{
    /**
     * Activate a PriceBook (draft → active) with approval.
     *
     * When activated, the PriceBook becomes the authoritative pricing document
     * for its market/channel/currency combination. Overlapping active PriceBooks
     * are automatically expired.
     *
     * @param  User  $approver  The user approving the activation
     *
     * @throws InvalidArgumentException If activation is not allowed
     */
    public function activate(PriceBook $priceBook, User $approver): PriceBook
    {
        if (! $priceBook->isDraft()) {
            throw new InvalidArgumentException(
                "Cannot activate PriceBook: current status '{$priceBook->status->label()}' is not Draft. "
                .'Only Draft PriceBooks can be activated.'
            );
        }

        if (! $priceBook->hasEntries()) {
            throw new InvalidArgumentException(
                'Cannot activate PriceBook: it must have at least one price entry. '
                .'Add prices before activating.'
            );
        }

        if (! $approver->canApprovePriceBooks()) {
            throw new InvalidArgumentException(
                'Cannot activate PriceBook: user does not have approval permissions. '
                .'Manager role or higher is required.'
            );
        }

        return DB::transaction(function () use ($priceBook, $approver): PriceBook {
            // Find and expire overlapping active PriceBooks
            $overlapping = $priceBook->findOverlappingActivePriceBooks();
            foreach ($overlapping as $overlappingPriceBook) {
                $this->expirePriceBook($overlappingPriceBook);
            }

            // Perform the activation
            $oldStatus = $priceBook->status;
            $priceBook->status = PriceBookStatus::Active;
            $priceBook->approved_at = now();
            $priceBook->approved_by = $approver->id;
            $priceBook->save();

            $this->logStatusTransition($priceBook, $oldStatus, PriceBookStatus::Active, $approver);

            return $priceBook->fresh() ?? $priceBook;
        });
    }

    /**
     * Archive a PriceBook (active/expired → archived).
     *
     * Archived PriceBooks are no longer used for pricing but are retained
     * for historical reference.
     *
     * @throws InvalidArgumentException If archiving is not allowed
     */
    public function archive(PriceBook $priceBook): PriceBook
    {
        if (! $priceBook->canBeArchived()) {
            throw new InvalidArgumentException(
                "Cannot archive PriceBook: current status '{$priceBook->status->label()}' does not allow archiving. "
                .'Only Active or Expired PriceBooks can be archived.'
            );
        }

        $oldStatus = $priceBook->status;
        $priceBook->status = PriceBookStatus::Archived;
        $priceBook->save();

        $this->logStatusTransition($priceBook, $oldStatus, PriceBookStatus::Archived);

        return $priceBook;
    }

    /**
     * Expire a PriceBook (active → expired).
     *
     * Used when a new PriceBook is activated for the same context,
     * or when the validity period ends.
     *
     * @throws InvalidArgumentException If expiring is not allowed
     */
    public function expirePriceBook(PriceBook $priceBook): PriceBook
    {
        if (! $priceBook->isActive()) {
            throw new InvalidArgumentException(
                "Cannot expire PriceBook: current status '{$priceBook->status->label()}' is not Active. "
                .'Only Active PriceBooks can be expired.'
            );
        }

        $oldStatus = $priceBook->status;
        $priceBook->status = PriceBookStatus::Expired;
        $priceBook->save();

        $this->logStatusTransition($priceBook, $oldStatus, PriceBookStatus::Expired);

        return $priceBook;
    }

    /**
     * Clone an existing PriceBook to a new draft with updated metadata.
     *
     * Creates a new draft PriceBook with all entries from the source,
     * but with new metadata (name, validity period, etc.).
     *
     * @param  array{name?: string, market?: string, channel_id?: string|null, currency?: string, valid_from?: Carbon|string, valid_to?: Carbon|string|null}  $newMetadata
     */
    public function cloneToNew(PriceBook $source, array $newMetadata = []): PriceBook
    {
        return DB::transaction(function () use ($source, $newMetadata): PriceBook {
            // Create the new PriceBook
            $newPriceBook = new PriceBook([
                'name' => $newMetadata['name'] ?? $source->name.' (Copy)',
                'market' => $newMetadata['market'] ?? $source->market,
                'channel_id' => array_key_exists('channel_id', $newMetadata) ? $newMetadata['channel_id'] : $source->channel_id,
                'currency' => $newMetadata['currency'] ?? $source->currency,
                'valid_from' => $newMetadata['valid_from'] ?? now(),
                'valid_to' => array_key_exists('valid_to', $newMetadata) ? $newMetadata['valid_to'] : $source->valid_to,
                'status' => PriceBookStatus::Draft,
            ]);
            $newPriceBook->save();

            // Clone all entries
            $source->entries->each(function (PriceBookEntry $entry) use ($newPriceBook): void {
                PriceBookEntry::create([
                    'price_book_id' => $newPriceBook->id,
                    'sellable_sku_id' => $entry->sellable_sku_id,
                    'base_price' => $entry->base_price,
                    'source' => PriceSource::Manual, // Cloned entries are considered manual
                    'policy_id' => null, // Reset policy reference
                ]);
            });

            // Log the creation
            $newPriceBook->auditLogs()->create([
                'event' => AuditLog::EVENT_CREATED,
                'old_values' => [],
                'new_values' => [
                    'cloned_from' => $source->id,
                    'cloned_from_name' => $source->name,
                    'entries_copied' => $source->entries()->count(),
                ],
                'user_id' => Auth::id(),
            ]);

            return $newPriceBook->fresh() ?? $newPriceBook;
        });
    }

    /**
     * Find the active PriceBook for a specific context.
     *
     * Returns the PriceBook that is currently active and valid for the
     * specified market, channel, and currency combination.
     */
    public function getActiveForContext(
        ?string $channelId,
        string $market,
        string $currency
    ): ?PriceBook {
        return PriceBook::query()
            ->where('status', PriceBookStatus::Active)
            ->where('market', $market)
            ->where('channel_id', $channelId)
            ->where('currency', $currency)
            ->where('valid_from', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->orderBy('valid_from', 'desc') // Most recent first
            ->first();
    }

    /**
     * Get the price for a specific Sellable SKU from a PriceBook.
     *
     * Returns the PriceBookEntry if the SKU has a price in the PriceBook,
     * or null if no price exists.
     */
    public function getPriceForSku(PriceBook $priceBook, SellableSku $sellableSku): ?PriceBookEntry
    {
        return $priceBook->entries()
            ->where('sellable_sku_id', $sellableSku->id)
            ->first();
    }

    /**
     * Get the base price value for a specific Sellable SKU from a PriceBook.
     *
     * Returns the decimal price value if the SKU has a price in the PriceBook,
     * or null if no price exists.
     */
    public function getBasePriceForSku(PriceBook $priceBook, SellableSku $sellableSku): ?string
    {
        $entry = $this->getPriceForSku($priceBook, $sellableSku);

        return $entry?->base_price;
    }

    /**
     * Check if a PriceBook can be activated.
     */
    public function canActivate(PriceBook $priceBook): bool
    {
        return $priceBook->isDraft() && $priceBook->hasEntries();
    }

    /**
     * Check if a PriceBook can be archived.
     */
    public function canArchive(PriceBook $priceBook): bool
    {
        return $priceBook->canBeArchived();
    }

    /**
     * Log a status transition to the audit log.
     */
    protected function logStatusTransition(
        PriceBook $priceBook,
        PriceBookStatus $oldStatus,
        PriceBookStatus $newStatus,
        ?User $approver = null
    ): void {
        $newValues = [
            'status' => $newStatus->value,
            'status_label' => $newStatus->label(),
        ];

        if ($approver !== null && $newStatus === PriceBookStatus::Active) {
            $newValues['approved_by'] = $approver->id;
            $newValues['approved_by_name'] = $approver->name;
            $newValues['approved_at'] = now()->toIso8601String();
        }

        $priceBook->auditLogs()->create([
            'event' => AuditLog::EVENT_STATUS_CHANGE,
            'old_values' => [
                'status' => $oldStatus->value,
                'status_label' => $oldStatus->label(),
            ],
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }
}
