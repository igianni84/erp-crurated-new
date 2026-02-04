<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Filament\Resources\Procurement\ProcurementIntentResource;
use App\Models\Procurement\ProcurementIntent;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewProcurementIntent extends ViewRecord
{
    protected static string $resource = ProcurementIntentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var ProcurementIntent $record */
        $record = $this->record;

        return "Intent #{$record->id}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var ProcurementIntent $record */
        $record = $this->record;

        return $record->getProductLabel().' - '.$record->status->label();
    }

    /**
     * Get the header actions for the view page.
     * Full implementation in US-014.
     *
     * @return array<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Actions will be implemented in US-014 (detail tabs with contextual actions)
        ];
    }
}
