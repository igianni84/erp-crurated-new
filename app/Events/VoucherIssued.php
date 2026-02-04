<?php

namespace App\Events;

use App\Models\Allocation\Allocation;
use App\Models\Allocation\Voucher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Event fired when vouchers are issued from an allocation.
 *
 * Dispatched after vouchers are successfully created in VoucherService::issueVouchers().
 * Used by Module D (Procurement) to auto-create ProcurementIntents.
 */
class VoucherIssued
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, Voucher>  $vouchers  The collection of issued vouchers
     * @param  Allocation  $allocation  The source allocation
     * @param  string  $saleReference  The sale reference for traceability
     */
    public function __construct(
        public Collection $vouchers,
        public Allocation $allocation,
        public string $saleReference
    ) {}
}
