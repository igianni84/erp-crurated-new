<?php

namespace App\Filament\Resources\Finance\RefundResource\Pages;

use App\Enums\Finance\RefundStatus;
use App\Filament\Resources\Finance\RefundResource;
use App\Models\Finance\Refund;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create action will be implemented in US-E070
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => Refund::count()),

            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RefundStatus::Pending))
                ->badge(fn (): int => Refund::where('status', RefundStatus::Pending)->count())
                ->badgeColor('warning'),

            'processed' => Tab::make('Processed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RefundStatus::Processed))
                ->badge(fn (): int => Refund::where('status', RefundStatus::Processed)->count())
                ->badgeColor('success'),

            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RefundStatus::Failed))
                ->badge(fn (): int => Refund::where('status', RefundStatus::Failed)->count())
                ->badgeColor('danger'),
        ];
    }
}
