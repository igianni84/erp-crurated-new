<?php

namespace App\Filament\Widgets\Procurement;

use App\Models\Procurement\BottlingInstruction;
use Filament\Widgets\Widget;

/**
 * Widget for displaying customer preference collection progress.
 *
 * This widget shows:
 * - A visual progress bar (collected / total vouchers)
 * - A list of vouchers with their preference status
 * - A link to the external customer portal
 */
class CustomerPreferenceCollectionWidget extends Widget
{
    /**
     * The view for the widget.
     */
    protected static string $view = 'filament.widgets.procurement.customer-preference-collection-widget';

    /**
     * The bottling instruction to display preferences for.
     */
    public BottlingInstruction $bottlingInstruction;

    /**
     * The sort order for the voucher list.
     */
    public string $sortOrder = 'all';

    /**
     * Number of items per page for the voucher list.
     */
    public int $perPage = 10;

    /**
     * Current page for pagination.
     */
    public int $currentPage = 1;

    /**
     * Get the preference progress data.
     *
     * @return array{collected: int, pending: int, total: int, percentage: float}
     */
    public function getProgressData(): array
    {
        $progress = $this->bottlingInstruction->getPreferenceProgress();
        $progress['percentage'] = $this->bottlingInstruction->getPreferenceProgressPercentage();

        return $progress;
    }

    /**
     * Get the progress bar color based on status.
     */
    public function getProgressColor(): string
    {
        $percentage = $this->bottlingInstruction->getPreferenceProgressPercentage();

        if ($percentage >= 100) {
            return 'success';
        }

        if ($percentage >= 50) {
            return 'warning';
        }

        if ($percentage > 0) {
            return 'info';
        }

        return 'gray';
    }

    /**
     * Get the filtered voucher list based on sort order.
     *
     * @return array<int, array{voucher_id: string, customer_name: string, preference_status: string, format_preference: string|null}>
     */
    public function getVoucherList(): array
    {
        $vouchers = $this->bottlingInstruction->getVoucherPreferenceList();

        // Filter based on sort order
        if ($this->sortOrder === 'collected') {
            $vouchers = array_filter($vouchers, fn ($v) => $v['preference_status'] === 'collected');
        } elseif ($this->sortOrder === 'pending') {
            $vouchers = array_filter($vouchers, fn ($v) => $v['preference_status'] === 'pending');
        }

        return array_values($vouchers);
    }

    /**
     * Get paginated voucher list.
     *
     * @return array<int, array{voucher_id: string, customer_name: string, preference_status: string, format_preference: string|null}>
     */
    public function getPaginatedVoucherList(): array
    {
        $vouchers = $this->getVoucherList();
        $offset = ($this->currentPage - 1) * $this->perPage;

        return array_slice($vouchers, $offset, $this->perPage);
    }

    /**
     * Get the total number of pages.
     */
    public function getTotalPages(): int
    {
        $total = count($this->getVoucherList());

        return (int) ceil($total / $this->perPage);
    }

    /**
     * Get the customer portal URL.
     */
    public function getPortalUrl(): string
    {
        return $this->bottlingInstruction->getCustomerPortalUrl();
    }

    /**
     * Check if the deadline has passed.
     */
    public function isDeadlinePassed(): bool
    {
        return $this->bottlingInstruction->isDeadlinePassed();
    }

    /**
     * Check if preferences can still be collected.
     */
    public function canCollectPreferences(): bool
    {
        return $this->bottlingInstruction->canCollectPreferences();
    }

    /**
     * Set the sort order.
     */
    public function setSortOrder(string $order): void
    {
        $this->sortOrder = $order;
        $this->currentPage = 1;
    }

    /**
     * Go to the next page.
     */
    public function nextPage(): void
    {
        if ($this->currentPage < $this->getTotalPages()) {
            $this->currentPage++;
        }
    }

    /**
     * Go to the previous page.
     */
    public function previousPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }
}
