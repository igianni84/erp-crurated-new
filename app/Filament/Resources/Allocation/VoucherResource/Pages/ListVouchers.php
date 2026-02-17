<?php

namespace App\Filament\Resources\Allocation\VoucherResource\Pages;

use App\Filament\Resources\Allocation\VoucherResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    /**
     * No header actions - vouchers cannot be created from the admin panel.
     * They are created only from sale confirmation via VoucherService.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
