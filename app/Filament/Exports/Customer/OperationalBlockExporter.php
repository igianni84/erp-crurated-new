<?php

namespace App\Filament\Exports\Customer;

use App\Models\Customer\Account;
use App\Models\Customer\Customer;
use App\Models\Customer\OperationalBlock;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OperationalBlockExporter extends Exporter
{
    protected static ?string $model = OperationalBlock::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('Block ID'),

            ExportColumn::make('blockable_type')
                ->label('Entity Type')
                ->formatStateUsing(function (string $state): string {
                    if ($state === Customer::class) {
                        return 'Customer';
                    }
                    if ($state === Account::class) {
                        return 'Account';
                    }

                    return $state;
                }),

            ExportColumn::make('blockable_name')
                ->label('Customer/Account Name')
                ->state(function (OperationalBlock $record): string {
                    $blockable = $record->blockable;
                    if ($blockable instanceof Customer) {
                        return $blockable->getName();
                    }
                    if ($blockable instanceof Account) {
                        $customer = $blockable->customer;

                        return $customer !== null
                            ? $customer->getName().' ('.$blockable->name.')'
                            : $blockable->name;
                    }

                    return '-';
                }),

            ExportColumn::make('block_type')
                ->label('Block Type')
                ->formatStateUsing(fn ($state): string => $state?->label() ?? '-'),

            ExportColumn::make('reason')
                ->label('Reason'),

            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn ($state): string => $state?->label() ?? '-'),

            ExportColumn::make('appliedByUser.name')
                ->label('Applied By'),

            ExportColumn::make('created_at')
                ->label('Applied At'),

            ExportColumn::make('removed_at')
                ->label('Removed At'),

            ExportColumn::make('removedByUser.name')
                ->label('Removed By'),

            ExportColumn::make('removal_reason')
                ->label('Removal Reason'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your operational blocks export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
