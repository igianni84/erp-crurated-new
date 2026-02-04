<?php

namespace App\Enums\Customer;

/**
 * Enum MembershipTier
 *
 * Membership tiers for customers in the system.
 * - Legacy: Grandfathered members with full access
 * - Member: Standard membership, approval required
 * - InvitationOnly: Access to exclusive products
 */
enum MembershipTier: string
{
    case Legacy = 'legacy';
    case Member = 'member';
    case InvitationOnly = 'invitation_only';

    /**
     * Get the human-readable label for this tier.
     */
    public function label(): string
    {
        return match ($this) {
            self::Legacy => 'Legacy',
            self::Member => 'Member',
            self::InvitationOnly => 'Invitation Only',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Legacy => 'warning',
            self::Member => 'primary',
            self::InvitationOnly => 'success',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Legacy => 'heroicon-o-star',
            self::Member => 'heroicon-o-user',
            self::InvitationOnly => 'heroicon-o-sparkles',
        };
    }

    /**
     * Get a description of this tier.
     */
    public function description(): string
    {
        return match ($this) {
            self::Legacy => 'Grandfathered members with full access to all channels and products.',
            self::Member => 'Standard membership tier requiring approval for access.',
            self::InvitationOnly => 'Exclusive membership with access to invitation-only products.',
        };
    }
}
