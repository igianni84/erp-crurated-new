<?php

namespace App\Filament\Resources\Allocation\VoucherTransferResource\Pages;

use App\Filament\Resources\Allocation\VoucherTransferResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVoucherTransfers extends ListRecords
{
    protected static string $resource = VoucherTransferResource::class;

    /**
     * No header actions - transfers are created from Voucher detail page.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
