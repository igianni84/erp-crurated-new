<?php

namespace App\Filament\Resources\Inventory\InboundBatchResource\Pages;

use App\Filament\Resources\Inventory\InboundBatchResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateInboundBatch extends CreateRecord
{
    protected static string $resource = InboundBatchResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Manual Inbound Batch Creation';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Admin-only manual batch creation - requires audit justification';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store the manual creation reason separately for audit
        // The reason field will be stored in condition_notes with a prefix
        if (isset($data['manual_creation_reason'])) {
            $reason = $data['manual_creation_reason'];
            $existingNotes = $data['condition_notes'] ?? '';

            $data['condition_notes'] = "[MANUAL CREATION - Reason: {$reason}]"
                .($existingNotes ? "\n\n{$existingNotes}" : '');

            unset($data['manual_creation_reason']);
        }

        return $data;
    }
}
