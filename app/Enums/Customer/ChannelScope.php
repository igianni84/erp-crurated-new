<?php

namespace App\Enums\Customer;

/**
 * Enum ChannelScope
 *
 * Defines the operational channel scope for an Account.
 *
 * Channel Eligibility Rules (US-016):
 * - B2C: Membership approved + no payment blocks
 * - B2B: Membership approved + customer_type = B2B + credit approved
 * - Club: Membership approved + (automatic tier access OR active club affiliation)
 */
enum ChannelScope: string
{
    case B2C = 'b2c';
    case B2B = 'b2b';
    case Club = 'club';

    /**
     * Get the human-readable label for this channel scope.
     */
    public function label(): string
    {
        return match ($this) {
            self::B2C => 'B2C',
            self::B2B => 'B2B',
            self::Club => 'Club',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::B2C => 'success',
            self::B2B => 'info',
            self::Club => 'warning',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::B2C => 'heroicon-o-user',
            self::B2B => 'heroicon-o-building-office',
            self::Club => 'heroicon-o-user-group',
        };
    }

    /**
     * Get the description of this channel.
     */
    public function description(): string
    {
        return match ($this) {
            self::B2C => 'Business-to-Consumer direct sales channel',
            self::B2B => 'Business-to-Business trade channel with credit terms',
            self::Club => 'Exclusive club membership channel',
        };
    }

    /**
     * Check if this channel requires customer type B2B.
     */
    public function requiresB2BCustomerType(): bool
    {
        return $this === self::B2B;
    }

    /**
     * Check if this channel requires credit approval.
     * Only B2B channel requires credit to be approved.
     */
    public function requiresCreditApproval(): bool
    {
        return $this === self::B2B;
    }

    /**
     * Check if this channel requires club affiliation.
     * Club channel requires either automatic tier access or active affiliation.
     */
    public function requiresClubAffiliation(): bool
    {
        return $this === self::Club;
    }

    /**
     * Check if this channel checks payment blocks.
     * B2C and B2B channels are affected by payment blocks.
     */
    public function checksPaymentBlocks(): bool
    {
        return $this === self::B2C || $this === self::B2B;
    }

    /**
     * Get the eligibility requirements as a human-readable list.
     *
     * @return array<string>
     */
    public function getEligibilityRequirements(): array
    {
        return match ($this) {
            self::B2C => [
                'Membership approved and active',
                'No payment blocks',
            ],
            self::B2B => [
                'Membership approved and active',
                'Customer type must be B2B',
                'Credit approved (credit limit set)',
                'No payment blocks',
            ],
            self::Club => [
                'Membership approved and active',
                'Automatic club access via tier (Legacy/InvitationOnly)',
                'OR active club affiliation',
            ],
        };
    }
}
