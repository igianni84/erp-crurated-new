<?php

namespace App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource\Pages;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Filament\Resources\Fulfillment\ShippingOrderExceptionResource;
use App\Models\Fulfillment\ShippingOrderException;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ViewShippingOrderException extends ViewRecord
{
    protected static string $resource = ShippingOrderExceptionResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var ShippingOrderException $record */
        $record = $this->record;

        return "Exception: #{$record->id}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Status Banner
                Section::make()
                    ->schema([
                        TextEntry::make('status')
                            ->label('')
                            ->badge()
                            ->size(TextSize::Large)
                            ->formatStateUsing(fn (ShippingOrderExceptionStatus $state): string => $state->label())
                            ->color(fn (ShippingOrderExceptionStatus $state): string => $state->color())
                            ->icon(fn (ShippingOrderExceptionStatus $state): string => $state->icon()),
                    ])
                    ->extraAttributes(fn (ShippingOrderException $record): array => [
                        'class' => $record->isActive()
                            ? 'bg-danger-50 dark:bg-danger-950 border-danger-200 dark:border-danger-800'
                            : 'bg-success-50 dark:bg-success-950 border-success-200 dark:border-success-800',
                    ]),

                // Exception Details
                Section::make('Exception Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Exception ID')
                                    ->copyable()
                                    ->copyMessage('Exception ID copied'),

                                TextEntry::make('exception_type')
                                    ->label('Exception Type')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state->label())
                                    ->color(fn ($state): string => $state->color())
                                    ->icon(fn ($state): string => $state->icon()),

                                TextEntry::make('shippingOrder.id')
                                    ->label('Shipping Order')
                                    ->url(fn (ShippingOrderException $record): ?string => $record->shippingOrder
                                        ? route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->shippingOrder])
                                        : null)
                                    ->color('primary'),

                                TextEntry::make('shippingOrderLine.id')
                                    ->label('Line ID')
                                    ->placeholder('Order-level exception'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('createdByUser.name')
                                    ->label('Created By')
                                    ->placeholder('System'),
                            ]),
                    ]),

                // Description
                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->label('')
                            ->columnSpanFull()
                            ->prose(),
                    ]),

                // Resolution Path
                Section::make('Resolution Path')
                    ->schema([
                        TextEntry::make('resolution_path')
                            ->label('')
                            ->columnSpanFull()
                            ->prose()
                            ->placeholder('No resolution path specified'),
                    ]),

                // Resolution Details (visible only if resolved)
                Section::make('Resolution Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('resolved_at')
                                    ->label('Resolved At')
                                    ->dateTime(),

                                TextEntry::make('resolvedByUser.name')
                                    ->label('Resolved By'),
                            ]),
                    ])
                    ->visible(fn (ShippingOrderException $record): bool => $record->isResolved()),

                // Blocking Warning
                Section::make()
                    ->schema([
                        TextEntry::make('blocking_warning')
                            ->label('')
                            ->getStateUsing(fn (): string => 'This exception is blocking the Shipping Order from progressing. It must be resolved before the SO can proceed.')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->color('danger'),
                    ])
                    ->visible(fn (ShippingOrderException $record): bool => $record->isBlocking())
                    ->extraAttributes([
                        'class' => 'bg-danger-50 dark:bg-danger-950 border-danger-300 dark:border-danger-700',
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Resolve Exception Action
            Action::make('resolve')
                ->label('Mark as Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ShippingOrderException $record): bool => $record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Resolve Exception')
                ->modalDescription('Are you sure you want to mark this exception as resolved? This indicates the issue has been addressed.')
                ->modalSubmitActionLabel('Mark as Resolved')
                ->action(function (ShippingOrderException $record): void {
                    $record->status = ShippingOrderExceptionStatus::Resolved;
                    $record->resolved_at = now();
                    $record->resolved_by = Auth::id();
                    $record->save();

                    Notification::make()
                        ->title('Exception Resolved')
                        ->body('The exception has been marked as resolved.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'resolved_at', 'resolved_by']);
                }),

            // View Shipping Order Action
            Action::make('view_so')
                ->label('View Shipping Order')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(fn (ShippingOrderException $record): ?string => $record->shippingOrder
                    ? route('filament.admin.resources.fulfillment.shipping-orders.view', ['record' => $record->shippingOrder])
                    : null)
                ->openUrlInNewTab(),
        ];
    }
}
