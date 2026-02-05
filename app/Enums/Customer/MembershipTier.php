<?php

namespace App\Enums\Customer;

/**
 * Enum MembershipTier
 *
 * Membership tiers for customers in the system.
 * - Legacy: Grandfathered members with full access to all channels
 * - Member: Standard membership with approval required, accesses standard channels
 * - InvitationOnly: Exclusive tier with access to invitation-only products and all channels
 *
 * Each tier influences channel eligibility:
 * - Legacy: Full access to B2C, B2B (if customer_type matches), and Club channels
 * - Member: Access to B2C and B2B (if customer_type matches), Club requires affiliation
 * - InvitationOnly: Full access to all channels including exclusive products
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

    /**
     * Get the channels this tier is eligible for (base eligibility).
     * Note: Additional factors (membership status, blocks, etc.) may further restrict access.
     *
     * @return array<ChannelScope>
     */
    public function eligibleChannels(): array
    {
        return match ($this) {
            // Legacy: Full access to all channels
            self::Legacy => [ChannelScope::B2C, ChannelScope::B2B, ChannelScope::Club],
            // Member: Standard channels, Club requires additional affiliation
            self::Member => [ChannelScope::B2C, ChannelScope::B2B],
            // InvitationOnly: Full access to all channels plus exclusive products
            self::InvitationOnly => [ChannelScope::B2C, ChannelScope::B2B, ChannelScope::Club],
        };
    }

    /**
     * Check if this tier allows access to a specific channel (base eligibility).
     * Note: This only checks tier-based eligibility. Other factors like
     * membership status, customer_type, blocks, and club affiliations may apply.
     */
    public function isEligibleForChannel(ChannelScope $channel): bool
    {
        return in_array($channel, $this->eligibleChannels(), true);
    }

    /**
     * Check if this tier grants automatic club access without explicit affiliation.
     * Legacy and InvitationOnly tiers have inherent club channel access.
     */
    public function hasAutomaticClubAccess(): bool
    {
        return match ($this) {
            self::Legacy => true,
            self::Member => false,
            self::InvitationOnly => true,
        };
    }

    /**
     * Check if this tier requires approval process.
     * Member tier requires standard approval, others may have expedited processes.
     */
    public function requiresApproval(): bool
    {
        return match ($this) {
            self::Legacy => false, // Grandfathered, no new approvals
            self::Member => true,  // Standard approval required
            self::InvitationOnly => true, // Invitation-based approval
        };
    }

    /**
     * Check if this tier has access to exclusive/invitation-only products.
     */
    public function hasExclusiveProductAccess(): bool
    {
        return match ($this) {
            self::Legacy => true,  // Full access includes exclusive
            self::Member => false, // Standard access only
            self::InvitationOnly => true, // Primary benefit of this tier
        };
    }

    /**
     * Get the priority/rank of this tier (higher = more privileged).
     * Used for comparisons and upgrade paths.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Member => 1,
            self::InvitationOnly => 2,
            self::Legacy => 3, // Highest as grandfathered with all benefits
        };
    }

    /**
     * Check if this tier is higher than another tier.
     */
    public function isHigherThan(MembershipTier $other): bool
    {
        return $this->priority() > $other->priority();
    }

    /**
     * Get channel eligibility reasons for this tier.
     * Returns human-readable explanations for eligibility decisions.
     *
     * @return array<string, array{eligible: bool, reason: string}>
     */
    public function getChannelEligibilityReasons(): array
    {
        return match ($this) {
            self::Legacy => [
                'b2c' => [
                    'eligible' => true,
                    'reason' => 'Legacy tier grants full B2C access.',
                ],
                'b2b' => [
                    'eligible' => true,
                    'reason' => 'Legacy tier grants B2B access (customer_type must be B2B).',
                ],
                'club' => [
                    'eligible' => true,
                    'reason' => 'Legacy tier grants automatic Club channel access.',
                ],
            ],
            self::Member => [
                'b2c' => [
                    'eligible' => true,
                    'reason' => 'Member tier allows B2C access when approved.',
                ],
                'b2b' => [
                    'eligible' => true,
                    'reason' => 'Member tier allows B2B access (customer_type must be B2B).',
                ],
                'club' => [
                    'eligible' => false,
                    'reason' => 'Member tier requires active Club affiliation for Club access.',
                ],
            ],
            self::InvitationOnly => [
                'b2c' => [
                    'eligible' => true,
                    'reason' => 'Invitation Only tier grants full B2C access.',
                ],
                'b2b' => [
                    'eligible' => true,
                    'reason' => 'Invitation Only tier grants B2B access (customer_type must be B2B).',
                ],
                'club' => [
                    'eligible' => true,
                    'reason' => 'Invitation Only tier grants automatic Club channel access.',
                ],
            ],
        };
    }
}
