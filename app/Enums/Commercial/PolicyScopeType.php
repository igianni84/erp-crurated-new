<?php

namespace App\Enums\Commercial;

/**
 * Enum PolicyScopeType
 *
 * Defines the scope type for pricing policy application.
 * Determines which Sellable SKUs are affected by a policy.
 */
enum PolicyScopeType: string
{
    case All = 'all';
    case Category = 'category';
    case Product = 'product';
    case Sku = 'sku';

    /**
     * Get the human-readable label for this scope type.
     */
    public function label(): string
    {
        return match ($this) {
            self::All => 'All SKUs',
            self::Category => 'Category',
            self::Product => 'Product',
            self::Sku => 'Specific SKU',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::All => 'success',
            self::Category => 'info',
            self::Product => 'warning',
            self::Sku => 'primary',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::All => 'heroicon-o-squares-2x2',
            self::Category => 'heroicon-o-folder',
            self::Product => 'heroicon-o-cube',
            self::Sku => 'heroicon-o-tag',
        };
    }

    /**
     * Get a description of what this scope type targets.
     */
    public function description(): string
    {
        return match ($this) {
            self::All => 'Applies to all commercially available SKUs',
            self::Category => 'Applies to SKUs within specific categories',
            self::Product => 'Applies to SKUs for specific products (all formats)',
            self::Sku => 'Applies to specific individual SKUs',
        };
    }
}
