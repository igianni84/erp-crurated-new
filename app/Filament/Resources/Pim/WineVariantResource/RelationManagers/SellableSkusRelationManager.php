<?php

namespace App\Filament\Resources\Pim\WineVariantResource\RelationManagers;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SellableSkusRelationManager extends RelationManager
{
    protected static string $relationship = 'sellableSkus';

    protected static ?string $title = 'Sellable SKUs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SKU Configuration')
                    ->description('Select the format and case configuration for this SKU')
                    ->schema([
                        Forms\Components\Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('case_configuration_id')
                            ->label('Case Configuration')
                            ->relationship(
                                'caseConfiguration',
                                'name',
                                fn ($query, $get) => $query->when(
                                    $get('format_id'),
                                    fn ($q, $formatId) => $q->where('format_id', $formatId)
                                )
                            )
                            ->getOptionLabelFromRecordUsing(function (CaseConfiguration $record): string {
                                /** @var 'owc'|'oc'|'none' $caseTypeValue */
                                $caseTypeValue = $record->case_type;
                                $caseType = match ($caseTypeValue) {
                                    'owc' => 'OWC',
                                    'oc' => 'OC',
                                    'none' => 'Loose',
                                };

                                return "{$record->name} ({$record->bottles_per_case}x {$caseType})";
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Identifiers')
                    ->schema([
                        Forms\Components\TextInput::make('sku_code')
                            ->label('SKU Code')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated based on wine, vintage, format, and case configuration'),
                        Forms\Components\TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255)
                            ->placeholder('e.g., EAN-13 or UPC'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Status & Integrity')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('lifecycle_status')
                                    ->label('Lifecycle Status')
                                    ->options([
                                        SellableSku::STATUS_DRAFT => 'Draft',
                                        SellableSku::STATUS_ACTIVE => 'Active',
                                        SellableSku::STATUS_RETIRED => 'Retired',
                                    ])
                                    ->default(SellableSku::STATUS_DRAFT)
                                    ->required()
                                    ->native(false),
                                Forms\Components\Select::make('source')
                                    ->label('Source')
                                    ->options([
                                        SellableSku::SOURCE_MANUAL => 'Manual',
                                        SellableSku::SOURCE_LIV_EX => 'Liv-ex',
                                        SellableSku::SOURCE_PRODUCER => 'Producer',
                                        SellableSku::SOURCE_GENERATED => 'Generated',
                                    ])
                                    ->default(SellableSku::SOURCE_MANUAL)
                                    ->required()
                                    ->native(false),
                            ]),
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_intrinsic')
                                    ->label('Intrinsic SKU')
                                    ->helperText('Original producer packaging configuration'),
                                Forms\Components\Toggle::make('is_producer_original')
                                    ->label('Producer Original')
                                    ->helperText('Exactly as released by the producer'),
                                Forms\Components\Toggle::make('is_verified')
                                    ->label('Verified')
                                    ->helperText('Configuration has been verified'),
                            ]),
                    ]),
                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->placeholder('Any additional notes about this SKU...'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku_code')
            ->columns([
                Tables\Columns\TextColumn::make('sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->description(function (SellableSku $record): string {
                        /** @var Format $format */
                        $format = $record->format;

                        return $format->volume_ml.' ml';
                    }),
                Tables\Columns\TextColumn::make('caseConfiguration.name')
                    ->label('Case')
                    ->sortable()
                    ->description(function (SellableSku $record): string {
                        /** @var CaseConfiguration $caseConfig */
                        $caseConfig = $record->caseConfiguration;
                        /** @var 'owc'|'oc'|'none' $caseTypeValue */
                        $caseTypeValue = $caseConfig->case_type;
                        $caseType = match ($caseTypeValue) {
                            'owc' => 'OWC',
                            'oc' => 'OC',
                            'none' => 'Loose',
                        };

                        return "{$caseConfig->bottles_per_case}x {$caseType}";
                    }),
                Tables\Columns\TextColumn::make('integrity_flags')
                    ->label('Integrity')
                    ->badge()
                    ->getStateUsing(function (SellableSku $record): string {
                        $flags = $record->getIntegrityFlags();

                        return count($flags) > 0 ? implode(', ', $flags) : '—';
                    })
                    ->color(fn (SellableSku $record): string => $record->hasIntegrityFlags() ? 'success' : 'gray')
                    ->icon(fn (SellableSku $record): ?string => $record->is_verified ? 'heroicon-o-check-badge' : null),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (SellableSku $record): string => $record->getSourceLabel())
                    ->color(fn (SellableSku $record): string => $record->getSourceColor())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SellableSku $record): string => $record->getStatusLabel())
                    ->color(fn (SellableSku $record): string => $record->getStatusColor())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        SellableSku::STATUS_DRAFT => 'Draft',
                        SellableSku::STATUS_ACTIVE => 'Active',
                        SellableSku::STATUS_RETIRED => 'Retired',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        SellableSku::SOURCE_MANUAL => 'Manual',
                        SellableSku::SOURCE_LIV_EX => 'Liv-ex',
                        SellableSku::SOURCE_PRODUCER => 'Producer',
                        SellableSku::SOURCE_GENERATED => 'Generated',
                    ]),
                Tables\Filters\TernaryFilter::make('is_intrinsic')
                    ->label('Intrinsic'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create SKU')
                    ->icon('heroicon-o-plus'),
                Tables\Actions\Action::make('generate_intrinsic')
                    ->label('Generate Intrinsic SKUs')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Intrinsic SKUs')
                    ->modalDescription('This will create SKUs based on standard producer configurations (6x750ml OWC, 12x750ml OWC, etc.). Existing SKUs with the same configuration will be skipped.')
                    ->action(function () {
                        $this->generateIntrinsicSkus();
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (SellableSku $record): bool => ! $record->isActive())
                        ->action(fn (SellableSku $record) => $this->activateSku($record)),
                    Tables\Actions\Action::make('retire')
                        ->label('Retire')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Retire SKU')
                        ->modalDescription('Are you sure you want to retire this SKU? It will no longer be available for sale.')
                        ->visible(fn (SellableSku $record): bool => $record->isActive())
                        ->action(fn (SellableSku $record) => $this->retireSku($record)),
                    Tables\Actions\Action::make('reactivate')
                        ->label('Reactivate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (SellableSku $record): bool => $record->isRetired())
                        ->action(fn (SellableSku $record) => $this->reactivateSku($record)),
                    Tables\Actions\Action::make('verify')
                        ->label('Mark Verified')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->visible(fn (SellableSku $record): bool => ! $record->is_verified)
                        ->action(function (SellableSku $record): void {
                            $record->is_verified = true;
                            $record->save();
                            Notification::make()
                                ->title('SKU Verified')
                                ->success()
                                ->send();
                        }),
                ])->label('Lifecycle')
                    ->icon('heroicon-o-arrow-path')
                    ->button()
                    ->size('sm'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $activated = 0;
                            foreach ($records as $record) {
                                if (! $record->isActive()) {
                                    $record->activate();
                                    $activated++;
                                }
                            }
                            Notification::make()
                                ->title("{$activated} SKU(s) Activated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_retire')
                        ->label('Retire Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $retired = 0;
                            foreach ($records as $record) {
                                if ($record->isActive()) {
                                    $record->retire();
                                    $retired++;
                                }
                            }
                            Notification::make()
                                ->title("{$retired} SKU(s) Retired")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_verify')
                        ->label('Mark Verified')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->action(function ($records): void {
                            $verified = 0;
                            foreach ($records as $record) {
                                if (! $record->is_verified) {
                                    $record->is_verified = true;
                                    $record->save();
                                    $verified++;
                                }
                            }
                            Notification::make()
                                ->title("{$verified} SKU(s) Verified")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Sellable SKUs')
            ->emptyStateDescription('Create SKUs to define how this wine can be sold.')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create SKU')
                    ->icon('heroicon-o-plus'),
                Tables\Actions\Action::make('generate_intrinsic_empty')
                    ->label('Generate Intrinsic SKUs')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->action(function () {
                        $this->generateIntrinsicSkus();
                    }),
            ]);
    }

    /**
     * Generate intrinsic SKUs based on standard producer configurations.
     */
    protected function generateIntrinsicSkus(): void
    {
        /** @var WineVariant $wineVariant */
        $wineVariant = $this->ownerRecord;

        // Standard intrinsic configurations
        $standardConfigs = [
            ['format_volume' => 750, 'bottles' => 6, 'case_type' => 'owc'],
            ['format_volume' => 750, 'bottles' => 12, 'case_type' => 'owc'],
            ['format_volume' => 1500, 'bottles' => 1, 'case_type' => 'owc'],
            ['format_volume' => 1500, 'bottles' => 3, 'case_type' => 'owc'],
            ['format_volume' => 3000, 'bottles' => 1, 'case_type' => 'owc'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($standardConfigs as $config) {
            // Find the format
            $format = Format::where('volume_ml', $config['format_volume'])->first();
            if ($format === null) {
                continue;
            }

            // Find matching case configuration
            $caseConfig = CaseConfiguration::where('format_id', $format->id)
                ->where('bottles_per_case', $config['bottles'])
                ->where('case_type', $config['case_type'])
                ->first();

            if ($caseConfig === null) {
                continue;
            }

            // Check if this SKU combination already exists
            $existingSku = SellableSku::where('wine_variant_id', $wineVariant->id)
                ->where('format_id', $format->id)
                ->where('case_configuration_id', $caseConfig->id)
                ->first();

            if ($existingSku !== null) {
                $skipped++;

                continue;
            }

            // Create the SKU
            SellableSku::create([
                'wine_variant_id' => $wineVariant->id,
                'format_id' => $format->id,
                'case_configuration_id' => $caseConfig->id,
                'lifecycle_status' => SellableSku::STATUS_DRAFT,
                'is_intrinsic' => true,
                'is_producer_original' => true,
                'is_verified' => false,
                'source' => SellableSku::SOURCE_GENERATED,
            ]);
            $created++;
        }

        if ($created > 0 || $skipped > 0) {
            Notification::make()
                ->title('Intrinsic SKUs Generated')
                ->body("{$created} SKU(s) created, {$skipped} already existed.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No SKUs Generated')
                ->body('No matching format/case configurations found. Make sure standard formats and case configurations are seeded.')
                ->warning()
                ->send();
        }
    }

    /**
     * Activate a SKU.
     */
    protected function activateSku(SellableSku $sku): void
    {
        $sku->activate();
        Notification::make()
            ->title('SKU Activated')
            ->body("SKU {$sku->sku_code} is now active.")
            ->success()
            ->send();
    }

    /**
     * Retire a SKU.
     */
    protected function retireSku(SellableSku $sku): void
    {
        $sku->retire();
        Notification::make()
            ->title('SKU Retired')
            ->body("SKU {$sku->sku_code} has been retired.")
            ->success()
            ->send();
    }

    /**
     * Reactivate a retired SKU.
     */
    protected function reactivateSku(SellableSku $sku): void
    {
        $sku->reactivate();
        Notification::make()
            ->title('SKU Reactivated')
            ->body("SKU {$sku->sku_code} has been reactivated.")
            ->success()
            ->send();
    }
}
