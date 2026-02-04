<?php

namespace App\Services\Customer;

use App\Enums\Customer\MembershipTier;
use App\Models\Customer\Customer;
use Carbon\Carbon;

/**
 * SegmentEngine
 *
 * Automatically derives customer segments based on behavioral and status data.
 *
 * Segment derivation factors:
 * - Spending history: total vouchers owned, case entitlements
 * - Membership tier: legacy, member, invitation_only
 * - Club affiliations: number of active clubs, club engagement
 * - Purchase frequency: voucher acquisition rate over time
 *
 * All segments are computed at runtime (not stored), ensuring they always reflect
 * the current state of the customer's data.
 *
 * Segment format:
 * - array<string, array{tag: string, label: string, criteria: string, priority: int}>
 * - tag: machine-readable segment identifier
 * - label: human-readable display name
 * - criteria: explanation of how the segment was derived
 * - priority: display priority (higher = more important)
 */
class SegmentEngine
{
    /**
     * Compute all applicable segments for a customer.
     *
     * @return array<string, array{tag: string, label: string, criteria: string, priority: int}>
     */
    public function compute(Customer $customer): array
    {
        $segments = [];

        // Derive segments from spending history
        $segments = array_merge($segments, $this->deriveSpendingSegments($customer));

        // Derive segments from membership tier
        $segments = array_merge($segments, $this->deriveMembershipSegments($customer));

        // Derive segments from club affiliations
        $segments = array_merge($segments, $this->deriveClubSegments($customer));

        // Derive segments from purchase frequency
        $segments = array_merge($segments, $this->deriveFrequencySegments($customer));

        // Sort by priority (descending)
        uasort($segments, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $segments;
    }

    /**
     * Get only the segment tags (for simple display or filtering).
     *
     * @return array<string>
     */
    public function getTags(Customer $customer): array
    {
        $segments = $this->compute($customer);

        return array_column($segments, 'tag');
    }

    /**
     * Get a summary of segments for display.
     *
     * @return array<string, string>
     */
    public function getSummary(Customer $customer): array
    {
        $segments = $this->compute($customer);
        $summary = [];

        foreach ($segments as $key => $segment) {
            $summary[$segment['tag']] = $segment['label'];
        }

        return $summary;
    }

    /**
     * Check if a customer has a specific segment.
     */
    public function hasSegment(Customer $customer, string $segmentTag): bool
    {
        $tags = $this->getTags($customer);

        return in_array($segmentTag, $tags, true);
    }

    /**
     * Get the criteria explanation for a specific segment.
     */
    public function getSegmentCriteria(Customer $customer, string $segmentTag): ?string
    {
        $segments = $this->compute($customer);

        foreach ($segments as $segment) {
            if ($segment['tag'] === $segmentTag) {
                return $segment['criteria'];
            }
        }

        return null;
    }

    /**
     * Derive spending-based segments.
     *
     * Segments:
     * - high_value: 50+ vouchers
     * - mid_value: 10-49 vouchers
     * - collector: 5+ case entitlements
     * - new_buyer: 1-9 vouchers and joined within last 6 months
     *
     * @return array<string, array{tag: string, label: string, criteria: string, priority: int}>
     */
    private function deriveSpendingSegments(Customer $customer): array
    {
        $segments = [];

        // Count vouchers (all states, as they represent purchases)
        $voucherCount = $customer->vouchers()->count();

        // Count case entitlements
        $caseCount = $customer->caseEntitlements()->count();

        // High Value Customer
        if ($voucherCount >= 50) {
            $segments['high_value'] = [
                'tag' => 'high_value',
                'label' => 'High Value',
                'criteria' => "Customer has {$voucherCount} vouchers (threshold: 50+).",
                'priority' => 100,
            ];
        } elseif ($voucherCount >= 10) {
            // Mid Value Customer
            $segments['mid_value'] = [
                'tag' => 'mid_value',
                'label' => 'Mid Value',
                'criteria' => "Customer has {$voucherCount} vouchers (threshold: 10-49).",
                'priority' => 80,
            ];
        } elseif ($voucherCount > 0 && $this->isRecentCustomer($customer)) {
            // New Buyer (has vouchers but is recent)
            $segments['new_buyer'] = [
                'tag' => 'new_buyer',
                'label' => 'New Buyer',
                'criteria' => "Customer has {$voucherCount} vouchers and joined within the last 6 months.",
                'priority' => 60,
            ];
        }

        // Collector (has multiple case entitlements)
        if ($caseCount >= 5) {
            $segments['collector'] = [
                'tag' => 'collector',
                'label' => 'Collector',
                'criteria' => "Customer has {$caseCount} case entitlements (threshold: 5+).",
                'priority' => 90,
            ];
        }

        return $segments;
    }

    /**
     * Derive membership tier-based segments.
     *
     * Segments:
     * - legacy_member: has Legacy tier
     * - vip: has InvitationOnly tier
     * - standard_member: has Member tier with approved status
     *
     * @return array<string, array{tag: string, label: string, criteria: string, priority: int}>
     */
    private function deriveMembershipSegments(Customer $customer): array
    {
        $segments = [];

        $membership = $customer->activeMembership;

        if ($membership === null) {
            return $segments;
        }

        $tier = $membership->tier;

        switch ($tier) {
            case MembershipTier::Legacy:
                $segments['legacy_member'] = [
                    'tag' => 'legacy_member',
                    'label' => 'Legacy Member',
                    'criteria' => 'Customer has grandfathered Legacy membership tier with full access.',
                    'priority' => 95,
                ];
                break;

            case MembershipTier::InvitationOnly:
                $segments['vip'] = [
                    'tag' => 'vip',
                    'label' => 'VIP',
                    'criteria' => 'Customer has Invitation Only tier with exclusive product access.',
                    'priority' => 98,
                ];
                break;

            case MembershipTier::Member:
                $segments['standard_member'] = [
                    'tag' => 'standard_member',
                    'label' => 'Standard Member',
                    'criteria' => 'Customer has approved standard Member tier.',
                    'priority' => 50,
                ];
                break;
        }

        return $segments;
    }

    /**
     * Derive club affiliation-based segments.
     *
     * Segments:
     * - multi_club: affiliated with 3+ clubs
     * - club_member: affiliated with 1-2 clubs
     *
     * @return array<string, array{tag: string, label: string, criteria: string, priority: int}>
     */
    private function deriveClubSegments(Customer $customer): array
    {
        $segments = [];

        $activeClubCount = $customer->effectiveClubAffiliations()->count();

        if ($activeClubCount >= 3) {
            $segments['multi_club'] = [
                'tag' => 'multi_club',
                'label' => 'Multi-Club',
                'criteria' => "Customer has {$activeClubCount} active club affiliations (threshold: 3+).",
                'priority' => 85,
            ];
        } elseif ($activeClubCount > 0) {
            $segments['club_member'] = [
                'tag' => 'club_member',
                'label' => 'Club Member',
                'criteria' => "Customer has {$activeClubCount} active club affiliation(s).",
                'priority' => 70,
            ];
        }

        return $segments;
    }

    /**
     * Derive purchase frequency-based segments.
     *
     * Segments:
     * - frequent_buyer: 5+ purchases in last 12 months
     * - regular_buyer: 2-4 purchases in last 12 months
     * - at_risk: no purchases in last 12 months but has historical purchases
     * - dormant: no purchases in last 24 months but has historical purchases
     *
     * @return array<string, array{tag: string, label: string, criteria: string, priority: int}>
     */
    private function deriveFrequencySegments(Customer $customer): array
    {
        $segments = [];

        $now = Carbon::now();
        $oneYearAgo = $now->copy()->subYear();
        $twoYearsAgo = $now->copy()->subYears(2);

        // Count vouchers created in last 12 months (as proxy for purchases)
        $recentPurchaseCount = $customer->vouchers()
            ->where('created_at', '>=', $oneYearAgo)
            ->count();

        // Count total historical vouchers
        $totalVoucherCount = $customer->vouchers()->count();

        // Check last purchase date
        $lastVoucher = $customer->vouchers()->orderBy('created_at', 'desc')->first();
        $lastPurchaseDate = $lastVoucher?->created_at;

        if ($recentPurchaseCount >= 5) {
            $segments['frequent_buyer'] = [
                'tag' => 'frequent_buyer',
                'label' => 'Frequent Buyer',
                'criteria' => "Customer made {$recentPurchaseCount} purchases in the last 12 months (threshold: 5+).",
                'priority' => 92,
            ];
        } elseif ($recentPurchaseCount >= 2) {
            $segments['regular_buyer'] = [
                'tag' => 'regular_buyer',
                'label' => 'Regular Buyer',
                'criteria' => "Customer made {$recentPurchaseCount} purchases in the last 12 months (threshold: 2-4).",
                'priority' => 75,
            ];
        } elseif ($totalVoucherCount > 0 && $recentPurchaseCount === 0) {
            // Has historical purchases but none in last year
            if ($lastPurchaseDate !== null && $lastPurchaseDate->lt($twoYearsAgo)) {
                $segments['dormant'] = [
                    'tag' => 'dormant',
                    'label' => 'Dormant',
                    'criteria' => 'Customer has not made any purchases in the last 24 months.',
                    'priority' => 40,
                ];
            } else {
                $segments['at_risk'] = [
                    'tag' => 'at_risk',
                    'label' => 'At Risk',
                    'criteria' => 'Customer has not made any purchases in the last 12 months.',
                    'priority' => 55,
                ];
            }
        }

        return $segments;
    }

    /**
     * Check if the customer joined within the last 6 months.
     */
    private function isRecentCustomer(Customer $customer): bool
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        return $customer->created_at->gte($sixMonthsAgo);
    }

    /**
     * Get all possible segment definitions.
     *
     * @return array<string, array{tag: string, label: string, description: string}>
     */
    public static function getSegmentDefinitions(): array
    {
        return [
            'high_value' => [
                'tag' => 'high_value',
                'label' => 'High Value',
                'description' => 'Customers with 50+ vouchers, representing significant spending history.',
            ],
            'mid_value' => [
                'tag' => 'mid_value',
                'label' => 'Mid Value',
                'description' => 'Customers with 10-49 vouchers, representing moderate spending history.',
            ],
            'collector' => [
                'tag' => 'collector',
                'label' => 'Collector',
                'description' => 'Customers with 5+ case entitlements, indicating preference for complete cases.',
            ],
            'new_buyer' => [
                'tag' => 'new_buyer',
                'label' => 'New Buyer',
                'description' => 'Customers who joined within last 6 months and have made purchases.',
            ],
            'legacy_member' => [
                'tag' => 'legacy_member',
                'label' => 'Legacy Member',
                'description' => 'Grandfathered members with Legacy tier enjoying full platform access.',
            ],
            'vip' => [
                'tag' => 'vip',
                'label' => 'VIP',
                'description' => 'Invitation Only tier members with access to exclusive products.',
            ],
            'standard_member' => [
                'tag' => 'standard_member',
                'label' => 'Standard Member',
                'description' => 'Standard approved members with regular platform access.',
            ],
            'multi_club' => [
                'tag' => 'multi_club',
                'label' => 'Multi-Club',
                'description' => 'Customers affiliated with 3 or more clubs.',
            ],
            'club_member' => [
                'tag' => 'club_member',
                'label' => 'Club Member',
                'description' => 'Customers with at least one active club affiliation.',
            ],
            'frequent_buyer' => [
                'tag' => 'frequent_buyer',
                'label' => 'Frequent Buyer',
                'description' => 'Customers making 5+ purchases in the last 12 months.',
            ],
            'regular_buyer' => [
                'tag' => 'regular_buyer',
                'label' => 'Regular Buyer',
                'description' => 'Customers making 2-4 purchases in the last 12 months.',
            ],
            'at_risk' => [
                'tag' => 'at_risk',
                'label' => 'At Risk',
                'description' => 'Customers with no purchases in the last 12 months but have historical purchases.',
            ],
            'dormant' => [
                'tag' => 'dormant',
                'label' => 'Dormant',
                'description' => 'Customers with no purchases in the last 24 months.',
            ],
        ];
    }
}
