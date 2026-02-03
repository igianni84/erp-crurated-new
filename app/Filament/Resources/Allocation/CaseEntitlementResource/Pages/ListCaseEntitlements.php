<?php

namespace App\Filament\Resources\Allocation\CaseEntitlementResource\Pages;

use App\Filament\Resources\Allocation\CaseEntitlementResource;
use Filament\Resources\Pages\ListRecords;

class ListCaseEntitlements extends ListRecords
{
    protected static string $resource = CaseEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        // No create action - case entitlements are created only via CaseEntitlementService
        return [];
    }
}
