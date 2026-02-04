<?php

namespace App\Services\Customer;

use App\Enums\Customer\ChannelScope;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\MembershipStatus;
use App\Models\Customer\Account;
use App\Models\Customer\Customer;

/**
 * EligibilityEngine
 *
 * Computes channel eligibility for a Customer or Account based on multiple factors:
 * - Membership tier and status
 * - Customer type (for B2B channel)
 * - Operational blocks (placeholder for US-027)
 * - Payment permissions (placeholder for US-018)
 * - Club affiliations (placeholder for US-022)
 *
 * The result is computed at runtime, not stored, ensuring real-time accuracy.
 */
class EligibilityEngine
{
    /**
     * Compute channel eligibility for a Customer or Account.
     *
     * @return array<string, array{eligible: bool, factors: array<string, array{positive: bool, reason: string}>}>
     */
    public function compute(Customer|Account $entity): array
    {
        $customer = $entity instanceof Account ? $entity->customer : $entity;
        $account = $entity instanceof Account ? $entity : null;

        $eligibility = [];

        foreach (ChannelScope::cases() as $channel) {
            $eligibility[$channel->value] = $this->computeChannelEligibility($customer, $account, $channel);
        }

        return $eligibility;
    }

    /**
     * Compute eligibility for a specific channel.
     *
     * @return array{eligible: bool, factors: array<string, array{positive: bool, reason: string}>}
     */
    private function computeChannelEligibility(Customer $customer, ?Account $account, ChannelScope $channel): array
    {
        $factors = [];

        // Factor 1: Membership status
        $factors['membership_status'] = $this->checkMembershipStatus($customer);

        // Factor 2: Membership tier eligibility for this channel
        $factors['membership_tier'] = $this->checkMembershipTier($customer, $channel);

        // Factor 3: Customer type requirement (for B2B)
        if ($channel === ChannelScope::B2B) {
            $factors['customer_type'] = $this->checkCustomerTypeForB2B($customer);
        }

        // Factor 4: Club affiliation (for Club channel, if Member tier)
        if ($channel === ChannelScope::Club) {
            $factors['club_affiliation'] = $this->checkClubAffiliation($customer);
        }

        // Factor 5: Operational blocks (placeholder for US-027)
        $factors['operational_blocks'] = $this->checkOperationalBlocks($customer, $account, $channel);

        // Factor 6: Payment permissions (placeholder for US-018, affects B2C and B2B)
        if ($channel === ChannelScope::B2C || $channel === ChannelScope::B2B) {
            $factors['payment_permissions'] = $this->checkPaymentPermissions($customer);
        }

        // Factor 7: Account-level restrictions (if computing for an Account)
        if ($account !== null) {
            $factors['account_status'] = $this->checkAccountStatus($account, $channel);
        }

        // Compute overall eligibility - all relevant factors must be positive
        $eligible = $this->areAllFactorsPositive($factors);

        return [
            'eligible' => $eligible,
            'factors' => $factors,
        ];
    }

    /**
     * Check membership status factor.
     * Membership must be Approved to access any channel.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkMembershipStatus(Customer $customer): array
    {
        $membership = $customer->membership;

        if ($membership === null) {
            return [
                'positive' => false,
                'reason' => 'No membership found. Apply for membership to access channels.',
            ];
        }

        if ($membership->status !== MembershipStatus::Approved) {
            $statusLabel = $membership->status->label();

            return [
                'positive' => false,
                'reason' => "Membership status is '{$statusLabel}'. Only approved memberships can access channels.",
            ];
        }

        // Check effective dates
        if ($membership->effective_from !== null && $membership->effective_from->isFuture()) {
            return [
                'positive' => false,
                'reason' => 'Membership is approved but not yet effective (starts '.$membership->effective_from->format('Y-m-d').').',
            ];
        }

        if ($membership->effective_to !== null && $membership->effective_to->isPast()) {
            return [
                'positive' => false,
                'reason' => 'Membership has expired (ended '.$membership->effective_to->format('Y-m-d').').',
            ];
        }

        return [
            'positive' => true,
            'reason' => 'Membership is approved and active.',
        ];
    }

    /**
     * Check membership tier eligibility for a channel.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkMembershipTier(Customer $customer, ChannelScope $channel): array
    {
        $membership = $customer->activeMembership;

        if ($membership === null) {
            return [
                'positive' => false,
                'reason' => 'No active membership to determine tier eligibility.',
            ];
        }

        $tier = $membership->tier;
        $isEligible = $tier->isEligibleForChannel($channel);
        $tierLabel = $tier->label();
        $channelLabel = $channel->label();

        if ($isEligible) {
            return [
                'positive' => true,
                'reason' => "{$tierLabel} tier grants access to {$channelLabel} channel.",
            ];
        }

        return [
            'positive' => false,
            'reason' => "{$tierLabel} tier does not include {$channelLabel} channel access.",
        ];
    }

    /**
     * Check if customer type allows B2B access.
     * B2B channel requires customer_type = B2B.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkCustomerTypeForB2B(Customer $customer): array
    {
        if ($customer->customer_type === CustomerType::B2B) {
            return [
                'positive' => true,
                'reason' => 'Customer type is B2B, eligible for B2B channel.',
            ];
        }

        $typeLabel = $customer->customer_type->label();

        return [
            'positive' => false,
            'reason' => "Customer type is '{$typeLabel}'. B2B channel requires B2B customer type.",
        ];
    }

    /**
     * Check club affiliation for Club channel access.
     * Member tier requires active club affiliation; Legacy/InvitationOnly get automatic access.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkClubAffiliation(Customer $customer): array
    {
        // Check if tier grants automatic club access
        $membership = $customer->activeMembership;

        if ($membership !== null && $membership->tier->hasAutomaticClubAccess()) {
            return [
                'positive' => true,
                'reason' => $membership->tier->label().' tier grants automatic Club channel access.',
            ];
        }

        // For Member tier, check for active club affiliation (placeholder for US-022)
        // Once CustomerClub model exists, this will check: $customer->clubs()->wherePivot('affiliation_status', 'active')->exists()
        $hasActiveAffiliation = $this->customerHasActiveClubAffiliation($customer);

        if ($hasActiveAffiliation) {
            return [
                'positive' => true,
                'reason' => 'Active club affiliation grants Club channel access.',
            ];
        }

        return [
            'positive' => false,
            'reason' => 'No active club affiliation. Join a club to access Club channel.',
        ];
    }

    /**
     * Check for operational blocks that would prevent channel access.
     * Placeholder for US-027 - returns positive until OperationalBlock model exists.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkOperationalBlocks(Customer $customer, ?Account $account, ChannelScope $channel): array
    {
        // Placeholder implementation until US-027 creates OperationalBlock model
        // Once implemented, will check for active blocks of type: payment, compliance, etc.
        $hasBlockingBlocks = $this->entityHasBlockingBlocks($customer, $account, $channel);

        if ($hasBlockingBlocks) {
            return [
                'positive' => false,
                'reason' => 'One or more operational blocks are preventing access to this channel.',
            ];
        }

        return [
            'positive' => true,
            'reason' => 'No operational blocks affecting this channel.',
        ];
    }

    /**
     * Check payment permissions for channel access.
     * Placeholder for US-018 - returns positive until PaymentPermission model exists.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkPaymentPermissions(Customer $customer): array
    {
        // Placeholder implementation until US-018 creates PaymentPermission model
        // Once implemented, will check: card_allowed, bank_transfer_allowed based on channel requirements
        $hasPaymentBlock = $this->customerHasPaymentBlock($customer);

        if ($hasPaymentBlock) {
            return [
                'positive' => false,
                'reason' => 'Payment permissions are restricting channel access.',
            ];
        }

        return [
            'positive' => true,
            'reason' => 'Payment permissions allow channel access.',
        ];
    }

    /**
     * Check account status and scope compatibility.
     *
     * @return array{positive: bool, reason: string}
     */
    private function checkAccountStatus(Account $account, ChannelScope $channel): array
    {
        // Check if account is active
        if (! $account->isActive()) {
            return [
                'positive' => false,
                'reason' => 'Account is '.$account->status->label().'. Only active accounts can operate.',
            ];
        }

        // Check if account's channel scope matches the requested channel
        if ($account->channel_scope !== $channel) {
            return [
                'positive' => false,
                'reason' => 'Account is scoped to '.$account->channel_scope->label().', not '.$channel->label().'.',
            ];
        }

        return [
            'positive' => true,
            'reason' => 'Account is active and scoped for '.$channel->label().' channel.',
        ];
    }

    /**
     * Check if all factors are positive.
     *
     * @param  array<string, array{positive: bool, reason: string}>  $factors
     */
    private function areAllFactorsPositive(array $factors): bool
    {
        foreach ($factors as $factor) {
            if (! $factor['positive']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Placeholder: Check if customer has active club affiliation.
     * Will be implemented in US-022 when CustomerClub model exists.
     */
    private function customerHasActiveClubAffiliation(Customer $customer): bool
    {
        // TODO: Implement when CustomerClub model exists (US-022)
        // return $customer->clubs()->wherePivot('affiliation_status', 'active')->exists();
        return false;
    }

    /**
     * Placeholder: Check if entity has blocking operational blocks.
     * Will be implemented in US-027 when OperationalBlock model exists.
     */
    private function entityHasBlockingBlocks(Customer $customer, ?Account $account, ChannelScope $channel): bool
    {
        // TODO: Implement when OperationalBlock model exists (US-027)
        // Block types that affect each channel:
        // - B2C: payment, compliance
        // - B2B: payment, compliance
        // - Club: compliance
        return false;
    }

    /**
     * Placeholder: Check if customer has payment block.
     * Will be implemented in US-018 when PaymentPermission model exists.
     */
    private function customerHasPaymentBlock(Customer $customer): bool
    {
        // TODO: Implement when PaymentPermission model exists (US-018)
        // Check if card_allowed = false when needed
        return false;
    }

    /**
     * Get a human-readable summary of eligibility for a Customer.
     *
     * @return array<string, string>
     */
    public function getSummary(Customer|Account $entity): array
    {
        $eligibility = $this->compute($entity);
        $summary = [];

        foreach ($eligibility as $channel => $data) {
            $status = $data['eligible'] ? 'Eligible' : 'Not Eligible';
            $negativeFactors = array_filter($data['factors'], fn ($f) => ! $f['positive']);

            if (empty($negativeFactors)) {
                $summary[$channel] = $status;
            } else {
                $reasons = array_map(fn ($f) => $f['reason'], $negativeFactors);
                $summary[$channel] = $status.': '.implode('; ', $reasons);
            }
        }

        return $summary;
    }
}
