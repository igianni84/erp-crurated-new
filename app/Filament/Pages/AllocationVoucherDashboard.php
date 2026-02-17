<?php

namespace App\Filament\Pages;

use App\Enums\Allocation\AllocationStatus;
use App\Enums\Allocation\ReservationStatus;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Allocation\VoucherTransferStatus;
use App\Filament\Resources\Allocation\AllocationResource;
use App\Filament\Resources\Allocation\VoucherResource;
use App\Filament\Resources\Allocation\VoucherTransferResource;
use App\Models\Allocation\Allocation;
use App\Models\Allocation\TemporaryReservation;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;

class AllocationVoucherDashboard extends Page
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'A&V Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Allocations';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Allocation & Voucher Dashboard';

    protected string $view = 'filament.pages.allocation-voucher-dashboard';

    // ========================================
    // Allocation Widgets Data
    // ========================================

    /**
     * Get allocation counts by status.
     *
     * @return array<string, int>
     */
    public function getAllocationStatusCounts(): array
    {
        $counts = [];
        foreach (AllocationStatus::cases() as $status) {
            $counts[$status->value] = Allocation::where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Get allocation status metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getAllocationStatusMeta(): array
    {
        $meta = [];
        foreach (AllocationStatus::cases() as $status) {
            $meta[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'icon' => $status->icon(),
            ];
        }

        return $meta;
    }

    /**
     * Get allocation summary metrics.
     *
     * @return array{total_active: int, near_exhaustion: int, closed_this_month: int}
     */
    public function getAllocationMetrics(): array
    {
        return [
            'total_active' => Allocation::where('status', AllocationStatus::Active->value)->count(),
            'near_exhaustion' => Allocation::where('status', AllocationStatus::Active->value)
                ->orWhere('status', AllocationStatus::Exhausted->value)
                ->get()
                ->filter(fn (Allocation $allocation) => $allocation->isNearExhaustion())
                ->count(),
            'closed_this_month' => Allocation::where('status', AllocationStatus::Closed->value)
                ->where('updated_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Get allocations that are near exhaustion (remaining < 10%).
     *
     * @return Collection<int, Allocation>
     */
    public function getNearExhaustionAllocations(): Collection
    {
        return Allocation::with(['wineVariant.wineMaster', 'format'])
            ->whereIn('status', [AllocationStatus::Active->value, AllocationStatus::Exhausted->value])
            ->get()
            ->filter(fn (Allocation $allocation) => $allocation->isNearExhaustion())
            ->take(10)
            ->values();
    }

    // ========================================
    // Voucher Widgets Data
    // ========================================

    /**
     * Get voucher counts by lifecycle state.
     *
     * @return array<string, int>
     */
    public function getVoucherStateCounts(): array
    {
        $counts = [];
        foreach (VoucherLifecycleState::cases() as $state) {
            $counts[$state->value] = Voucher::where('lifecycle_state', $state->value)->count();
        }

        return $counts;
    }

    /**
     * Get voucher state metadata for display.
     *
     * @return array<string, array{label: string, color: string, icon: string}>
     */
    public function getVoucherStateMeta(): array
    {
        $meta = [];
        foreach (VoucherLifecycleState::cases() as $state) {
            $meta[$state->value] = [
                'label' => $state->label(),
                'color' => $state->color(),
                'icon' => $state->icon(),
            ];
        }

        return $meta;
    }

    /**
     * Get voucher summary metrics.
     *
     * @return array{total_issued: int, pending_redemption: int, redeemed_this_month: int}
     */
    public function getVoucherMetrics(): array
    {
        return [
            'total_issued' => Voucher::where('lifecycle_state', VoucherLifecycleState::Issued->value)->count(),
            'pending_redemption' => Voucher::where('lifecycle_state', VoucherLifecycleState::Locked->value)->count(),
            'redeemed_this_month' => Voucher::where('lifecycle_state', VoucherLifecycleState::Redeemed->value)
                ->where('updated_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
        ];
    }

    // ========================================
    // Reservation Widgets Data
    // ========================================

    /**
     * Get reservation summary metrics.
     *
     * @return array{active_count: int, expired_today: int}
     */
    public function getReservationMetrics(): array
    {
        return [
            'active_count' => TemporaryReservation::where('status', ReservationStatus::Active->value)->count(),
            'expired_today' => TemporaryReservation::where('status', ReservationStatus::Expired->value)
                ->whereDate('updated_at', Carbon::today())
                ->count(),
        ];
    }

    // ========================================
    // Transfer Widgets Data
    // ========================================

    /**
     * Get transfer summary metrics.
     *
     * @return array{pending_count: int, failed_transfers: int}
     */
    public function getTransferMetrics(): array
    {
        return [
            'pending_count' => VoucherTransfer::where('status', VoucherTransferStatus::Pending->value)->count(),
            'failed_transfers' => VoucherTransfer::whereIn('status', [
                VoucherTransferStatus::Cancelled->value,
                VoucherTransferStatus::Expired->value,
            ])
                ->where('updated_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Get expired transfers from today.
     *
     * @return Collection<int, VoucherTransfer>
     */
    public function getExpiredTransfersToday(): Collection
    {
        return VoucherTransfer::with(['voucher', 'fromCustomer', 'toCustomer'])
            ->where('status', VoucherTransferStatus::Expired->value)
            ->whereDate('updated_at', Carbon::today())
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();
    }

    // ========================================
    // Quick Links and URLs
    // ========================================

    /**
     * Get URL to allocations list with near exhaustion filter.
     */
    public function getNearExhaustionAllocationsUrl(): string
    {
        return AllocationResource::getUrl('index', [
            'filters' => [
                'near_exhaustion' => true,
            ],
        ]);
    }

    /**
     * Get URL to allocations list with active status filter.
     */
    public function getActiveAllocationsUrl(): string
    {
        return AllocationResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'values' => [AllocationStatus::Active->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to allocations list with closed status filter.
     */
    public function getClosedAllocationsUrl(): string
    {
        return AllocationResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'values' => [AllocationStatus::Closed->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to vouchers list.
     */
    public function getVouchersUrl(): string
    {
        return VoucherResource::getUrl('index');
    }

    /**
     * Get URL to vouchers list with issued state filter.
     */
    public function getIssuedVouchersUrl(): string
    {
        return VoucherResource::getUrl('index', [
            'filters' => [
                'lifecycle_state' => [
                    'values' => [VoucherLifecycleState::Issued->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to vouchers list with locked (pending redemption) state filter.
     */
    public function getPendingRedemptionVouchersUrl(): string
    {
        return VoucherResource::getUrl('index', [
            'filters' => [
                'lifecycle_state' => [
                    'values' => [VoucherLifecycleState::Locked->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to vouchers list with redeemed state filter.
     */
    public function getRedeemedVouchersUrl(): string
    {
        return VoucherResource::getUrl('index', [
            'filters' => [
                'lifecycle_state' => [
                    'values' => [VoucherLifecycleState::Redeemed->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to transfers list with pending status filter.
     */
    public function getPendingTransfersUrl(): string
    {
        return VoucherTransferResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'values' => [VoucherTransferStatus::Pending->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to transfers list with expired status filter.
     */
    public function getExpiredTransfersUrl(): string
    {
        return VoucherTransferResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'values' => [VoucherTransferStatus::Expired->value],
                ],
            ],
        ]);
    }

    /**
     * Get URL to view a specific allocation.
     */
    public function getAllocationViewUrl(Allocation $allocation): string
    {
        return AllocationResource::getUrl('view', ['record' => $allocation]);
    }

    /**
     * Get URL to view a specific voucher transfer.
     */
    public function getTransferViewUrl(VoucherTransfer $transfer): string
    {
        return VoucherTransferResource::getUrl('view', ['record' => $transfer]);
    }
}
