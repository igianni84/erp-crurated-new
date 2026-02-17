<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\Customer\Party;
use App\Models\Procurement\ProducerSupplierConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing ProducerSupplierConfig lifecycle.
 *
 * Centralizes all supplier/producer configuration logic including
 * creation, updates, and retrieving applicable defaults for products.
 */
class ProducerSupplierConfigService
{
    /**
     * Get existing config for a party, or create a new one if none exists.
     *
     * This method ensures there's always a config available for a party,
     * creating an empty one if necessary.
     *
     * @throws InvalidArgumentException If party is not a supplier or producer
     */
    public function getOrCreate(Party $party): ProducerSupplierConfig
    {
        // Validate that the party is a supplier or producer
        if (! $party->isSupplier() && ! $party->isProducer()) {
            throw new InvalidArgumentException(
                "Cannot get/create config for party '{$party->legal_name}': party must have Supplier or Producer role."
            );
        }

        // Check for existing config
        $existingConfig = $party->supplierConfig;

        if ($existingConfig !== null) {
            return $existingConfig;
        }

        // Create a new config
        return DB::transaction(function () use ($party): ProducerSupplierConfig {
            $config = ProducerSupplierConfig::create([
                'party_id' => $party->id,
                'default_bottling_deadline_days' => null,
                'allowed_formats' => null,
                'serialization_constraints' => null,
                'notes' => null,
            ]);

            $this->logCreation($config);

            return $config;
        });
    }

    /**
     * Update a supplier/producer config with new data.
     *
     * Creates an audit log entry for the update.
     *
     * @param  array<string, mixed>  $data  Updatable fields: default_bottling_deadline_days, allowed_formats, serialization_constraints, notes
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function update(ProducerSupplierConfig $config, array $data): ProducerSupplierConfig
    {
        // Validate deadline_days if provided
        if (array_key_exists('default_bottling_deadline_days', $data)) {
            $deadlineDays = $data['default_bottling_deadline_days'];
            if ($deadlineDays !== null && (! is_int($deadlineDays) || $deadlineDays <= 0)) {
                throw new InvalidArgumentException(
                    'default_bottling_deadline_days must be a positive integer or null.'
                );
            }
        }

        // Validate allowed_formats if provided (should be array or null)
        if (array_key_exists('allowed_formats', $data)) {
            $allowedFormats = $data['allowed_formats'];
            if ($allowedFormats !== null && ! is_array($allowedFormats)) {
                throw new InvalidArgumentException(
                    'allowed_formats must be an array of format strings or null.'
                );
            }
        }

        // Validate serialization_constraints if provided (should be array or null)
        if (array_key_exists('serialization_constraints', $data)) {
            $serializationConstraints = $data['serialization_constraints'];
            if ($serializationConstraints !== null && ! is_array($serializationConstraints)) {
                throw new InvalidArgumentException(
                    'serialization_constraints must be an array or null.'
                );
            }
        }

        // Capture old values for audit
        $oldValues = [
            'default_bottling_deadline_days' => $config->default_bottling_deadline_days,
            'allowed_formats' => $config->allowed_formats,
            'serialization_constraints' => $config->serialization_constraints,
            'notes' => $config->notes,
        ];

        // Filter to only allowed fields
        $allowedFields = [
            'default_bottling_deadline_days',
            'allowed_formats',
            'serialization_constraints',
            'notes',
        ];

        $filteredData = array_intersect_key($data, array_flip($allowedFields));

        // Only update if there's actual data to update
        if ($filteredData === []) {
            return $config;
        }

        return DB::transaction(function () use ($config, $filteredData, $oldValues): ProducerSupplierConfig {
            $config->update($filteredData);

            $this->logUpdate($config, $oldValues);

            return $config->fresh() ?? $config;
        });
    }

    /**
     * Get applicable defaults for a product from a party's config.
     *
     * Returns an array with default values that can be used when creating
     * bottling instructions or other procurement entities related to this
     * supplier/producer.
     *
     * @param  \App\Models\Product\SellableSku|\App\Models\Product\LiquidProduct|mixed  $product  The product to get defaults for
     * @return array{default_bottling_deadline_days: int|null, default_bottling_deadline_date: Carbon|null, allowed_formats: array<int, string>|null, serialization_constraints: array<string, mixed>|null, authorized_serialization_locations: array<int, string>|null, required_serialization_location: string|null, has_config: bool}
     */
    public function getDefaultsForProduct(Party $party, mixed $product): array
    {
        $config = $party->supplierConfig;

        $defaultResponse = [
            'default_bottling_deadline_days' => null,
            'default_bottling_deadline_date' => null,
            'allowed_formats' => null,
            'serialization_constraints' => null,
            'authorized_serialization_locations' => null,
            'required_serialization_location' => null,
            'has_config' => false,
        ];

        if ($config === null) {
            return $defaultResponse;
        }

        // Get serialization constraint details
        $authorizedLocations = $config->getSerializationConstraint('authorized_locations');
        $requiredLocation = $config->getSerializationConstraint('required_location');

        // Build the response with actual config values
        $response = [
            'default_bottling_deadline_days' => $config->default_bottling_deadline_days,
            'default_bottling_deadline_date' => $config->getDefaultBottlingDeadlineDate(),
            'allowed_formats' => $config->allowed_formats,
            'serialization_constraints' => $config->serialization_constraints,
            'authorized_serialization_locations' => is_array($authorizedLocations) ? $authorizedLocations : null,
            'required_serialization_location' => is_string($requiredLocation) ? $requiredLocation : null,
            'has_config' => true,
        ];

        // Future enhancement: could filter/adjust defaults based on product type
        // For example, different deadline rules for liquid vs bottle products
        // For now, we return the same defaults regardless of product type

        return $response;
    }

    /**
     * Check if a format is allowed for a party.
     *
     * Returns true if no restrictions are configured or if the format is in the allowed list.
     */
    public function isFormatAllowedForParty(Party $party, string $format): bool
    {
        $config = $party->supplierConfig;

        if ($config === null) {
            // No config means no restrictions
            return true;
        }

        return $config->isFormatAllowed($format);
    }

    /**
     * Check if a serialization location is authorized for a party.
     *
     * Returns true if no restrictions are configured or if the location is authorized.
     */
    public function isSerializationLocationAuthorizedForParty(Party $party, string $location): bool
    {
        $config = $party->supplierConfig;

        if ($config === null) {
            // No config means no restrictions
            return true;
        }

        return $config->isSerializationLocationAuthorized($location);
    }

    /**
     * Get the default bottling deadline for a party.
     *
     * Returns null if no config exists or no deadline is configured.
     */
    public function getDefaultBottlingDeadlineForParty(Party $party): ?int
    {
        $config = $party->supplierConfig;

        if ($config === null) {
            return null;
        }

        return $config->default_bottling_deadline_days;
    }

    /**
     * Log the creation of a config.
     */
    protected function logCreation(ProducerSupplierConfig $config): void
    {
        $config->auditLogs()->create([
            'event' => AuditLog::EVENT_CREATED,
            'new_values' => [
                'party_id' => $config->party_id,
                'default_bottling_deadline_days' => $config->default_bottling_deadline_days,
                'allowed_formats' => $config->allowed_formats,
                'serialization_constraints' => $config->serialization_constraints,
                'notes' => $config->notes,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Log an update to the audit log.
     *
     * @param  array<string, mixed>  $oldValues
     */
    protected function logUpdate(ProducerSupplierConfig $config, array $oldValues): void
    {
        $newValues = [
            'default_bottling_deadline_days' => $config->default_bottling_deadline_days,
            'allowed_formats' => $config->allowed_formats,
            'serialization_constraints' => $config->serialization_constraints,
            'notes' => $config->notes,
        ];

        // Only include fields that actually changed
        $changedOldValues = [];
        $changedNewValues = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changedOldValues[$key] = $oldValue;
                $changedNewValues[$key] = $newValue;
            }
        }

        // Only create audit log if something actually changed
        if ($changedNewValues !== []) {
            $config->auditLogs()->create([
                'event' => AuditLog::EVENT_UPDATED,
                'old_values' => $changedOldValues,
                'new_values' => $changedNewValues,
                'user_id' => Auth::id(),
            ]);
        }
    }
}
