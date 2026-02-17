<?php

namespace App\Filament\Resources\Pim\WineVariantResource\RelationManagers;

use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\CompositeSkuItem;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use RuntimeException;

class SellableSkusRelationManager extends RelationManager
{
    protected static string $relationship = 'sellableSkus';

    protected static ?string $title = 'Sellable SKUs';

    public function form(Schema $schema): Schema
    {
        /** @var WineVariant $wineVariant */
        $wineVariant = $this->ownerRecord;

        return $schema
            ->components([
                Section::make('SKU Type')
                    ->description('Choose whether this is a standard SKU or a composite bundle')
                    ->schema([
                        Toggle::make('is_composite')
                            ->label('Composite SKU (Bundle)')
                            ->helperText('A composite SKU is made up of multiple other SKUs sold as an indivisible bundle')
                            ->live()
                            ->afterStateUpdated(function (Set $set, bool $state): void {
                                if ($state) {
                                    // Clear format/case config when switching to composite
                                    $set('format_id', null);
                                    $set('case_configuration_id', null);
                                }
                            }),
                    ]),
                Section::make('SKU Configuration')
                    ->description('Select the format and case configuration for this SKU')
                    ->schema([
                        Select::make('format_id')
                            ->label('Format')
                            ->relationship('format', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Format $record): string => "{$record->name} ({$record->volume_ml} ml)")
                            ->required(fn (Get $get): bool => ! $get('is_composite'))
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('case_configuration_id')
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
                            ->required(fn (Get $get): bool => ! $get('is_composite'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => ! $get('is_composite')),
                Section::make('Bundle Composition')
                    ->description('Select the SKUs that make up this bundle. All component SKUs must be active for the bundle to be activated.')
                    ->schema([
                        Repeater::make('compositeItems')
                            ->label('Component SKUs')
                            ->relationship()
                            ->schema([
                                Select::make('sellable_sku_id')
                                    ->label('SKU')
                                    ->options(function () use ($wineVariant): array {
                                        // Get all non-composite SKUs from this wine variant
                                        return SellableSku::where('wine_variant_id', $wineVariant->id)
                                            ->where('is_composite', false)
                                            ->get()
                                            ->mapWithKeys(function (SellableSku $sku): array {
                                                $statusBadge = $sku->isActive() ? '(Active)' : '('.$sku->getStatusLabel().')';

                                                return [$sku->id => "{$sku->sku_code} {$statusBadge}"];
                                            })
                                            ->toArray();
                                    })
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add Component SKU')
                            ->reorderable(false)
                            ->minItems(1)
                            ->helperText('Warning: Only active component SKUs can be part of an active bundle.'),
                    ])
                    ->visible(fn (Get $get): bool => (bool) $get('is_composite')),
                Section::make('Identifiers')
                    ->schema([
                        TextInput::make('sku_code')
                            ->label('SKU Code')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated based on wine, vintage, format, and case configuration'),
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255)
                            ->placeholder('e.g., EAN-13 or UPC'),
                    ])
                    ->columns(2),
                Section::make('Status & Integrity')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('lifecycle_status')
                                    ->label('Lifecycle Status')
                                    ->options([
                                        SellableSku::STATUS_DRAFT => 'Draft',
                                        SellableSku::STATUS_ACTIVE => 'Active',
                                        SellableSku::STATUS_RETIRED => 'Retired',
                                    ])
                                    ->default(SellableSku::STATUS_DRAFT)
                                    ->required()
                                    ->native(false),
                                Select::make('source')
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
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_intrinsic')
                                    ->label('Intrinsic SKU')
                                    ->helperText('Original producer packaging configuration'),
                                Toggle::make('is_producer_original')
                                    ->label('Producer Original')
                                    ->helperText('Exactly as released by the producer'),
                                Toggle::make('is_verified')
                                    ->label('Verified')
                                    ->helperText('Configuration has been verified'),
                            ]),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
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
                TextColumn::make('sku_code')
                    ->label('SKU Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->icon(fn (SellableSku $record): ?string => $record->isComposite() ? 'heroicon-o-cube-transparent' : null)
                    ->iconPosition('before'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (SellableSku $record): string => $record->isComposite() ? 'Bundle' : 'Standard')
                    ->color(fn (SellableSku $record): string => $record->isComposite() ? 'warning' : 'gray'),
                TextColumn::make('format.name')
                    ->label('Format')
                    ->sortable()
                    ->getStateUsing(function (SellableSku $record): string {
                        if ($record->isComposite()) {
                            return $record->getCompositeDescription() ?: 'No components';
                        }
                        /** @var Format|null $format */
                        $format = $record->format;

                        return $format !== null ? $format->name : '—';
                    })
                    ->description(function (SellableSku $record): string {
                        if ($record->isComposite()) {
                            $total = $record->getCompositeTotalBottles();

                            return $total > 0 ? "{$total} bottles total" : '';
                        }
                        /** @var Format|null $format */
                        $format = $record->format;

                        return $format !== null ? $format->volume_ml.' ml' : '';
                    }),
                TextColumn::make('caseConfiguration.name')
                    ->label('Case')
                    ->sortable()
                    ->getStateUsing(function (SellableSku $record): string {
                        if ($record->isComposite()) {
                            $count = $record->compositeItems()->count();

                            return "{$count} component(s)";
                        }
                        /** @var CaseConfiguration|null $caseConfig */
                        $caseConfig = $record->caseConfiguration;

                        return $caseConfig !== null ? $caseConfig->name : '—';
                    })
                    ->description(function (SellableSku $record): string {
                        if ($record->isComposite()) {
                            if (! $record->hasAllActiveComponents()) {
                                return 'Has inactive components';
                            }

                            return 'All components active';
                        }
                        /** @var CaseConfiguration|null $caseConfig */
                        $caseConfig = $record->caseConfiguration;
                        if ($caseConfig === null) {
                            return '';
                        }
                        /** @var 'owc'|'oc'|'none' $caseTypeValue */
                        $caseTypeValue = $caseConfig->case_type;
                        $caseType = match ($caseTypeValue) {
                            'owc' => 'OWC',
                            'oc' => 'OC',
                            'none' => 'Loose',
                        };

                        return "{$caseConfig->bottles_per_case}x {$caseType}";
                    })
                    ->color(function (SellableSku $record): ?string {
                        if ($record->isComposite() && ! $record->hasAllActiveComponents()) {
                            return 'danger';
                        }

                        return null;
                    }),
                TextColumn::make('integrity_flags')
                    ->label('Integrity')
                    ->badge()
                    ->getStateUsing(function (SellableSku $record): string {
                        $flags = $record->getIntegrityFlags();

                        return count($flags) > 0 ? implode(', ', $flags) : '—';
                    })
                    ->color(fn (SellableSku $record): string => $record->hasIntegrityFlags() ? 'success' : 'gray')
                    ->icon(fn (SellableSku $record): ?string => $record->is_verified ? 'heroicon-o-check-badge' : null),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (SellableSku $record): string => $record->getSourceLabel())
                    ->color(fn (SellableSku $record): string => $record->getSourceColor())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SellableSku $record): string => $record->getStatusLabel())
                    ->color(fn (SellableSku $record): string => $record->getStatusColor())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('format_id')
                    ->label('Format')
                    ->relationship('format', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options([
                        SellableSku::STATUS_DRAFT => 'Draft',
                        SellableSku::STATUS_ACTIVE => 'Active',
                        SellableSku::STATUS_RETIRED => 'Retired',
                    ]),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        SellableSku::SOURCE_MANUAL => 'Manual',
                        SellableSku::SOURCE_LIV_EX => 'Liv-ex',
                        SellableSku::SOURCE_PRODUCER => 'Producer',
                        SellableSku::SOURCE_GENERATED => 'Generated',
                    ]),
                TernaryFilter::make('is_intrinsic')
                    ->label('Intrinsic'),
                TernaryFilter::make('is_verified')
                    ->label('Verified'),
                TernaryFilter::make('is_composite')
                    ->label('Composite/Bundle'),
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create SKU')
                    ->icon('heroicon-o-plus'),
                CreateAction::make('create_composite')
                    ->label('Create Bundle')
                    ->icon('heroicon-o-cube-transparent')
                    ->color('warning')
                    ->mutateDataUsing(function (array $data): array {
                        $data['is_composite'] = true;
                        // Composite SKUs don't need format/case config
                        $data['format_id'] = null;
                        $data['case_configuration_id'] = null;

                        return $data;
                    })
                    ->using(function (array $data): SellableSku {
                        /** @var WineVariant $wineVariant */
                        $wineVariant = $this->ownerRecord;

                        // Create the composite SKU
                        $sku = new SellableSku;
                        $sku->wine_variant_id = $wineVariant->id;
                        $sku->is_composite = true;
                        $sku->lifecycle_status = SellableSku::STATUS_DRAFT;
                        $sku->source = $data['source'] ?? SellableSku::SOURCE_MANUAL;
                        $sku->barcode = $data['barcode'] ?? null;
                        $sku->notes = $data['notes'] ?? null;
                        $sku->is_intrinsic = $data['is_intrinsic'] ?? false;
                        $sku->is_producer_original = $data['is_producer_original'] ?? false;
                        $sku->is_verified = $data['is_verified'] ?? false;
                        // Generate SKU code for composite
                        $sku->sku_code = $this->generateCompositeSkuCode($wineVariant);
                        $sku->save();

                        // Create composite items
                        if (isset($data['compositeItems']) && is_array($data['compositeItems'])) {
                            foreach ($data['compositeItems'] as $item) {
                                if (! empty($item['sellable_sku_id'])) {
                                    CompositeSkuItem::create([
                                        'composite_sku_id' => $sku->id,
                                        'sellable_sku_id' => $item['sellable_sku_id'],
                                        'quantity' => $item['quantity'] ?? 1,
                                    ]);
                                }
                            }
                        }

                        return $sku;
                    }),
                Action::make('generate_intrinsic')
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
            ->recordActions([
                ActionGroup::make([
                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (SellableSku $record): string => $record->isComposite() ? 'Activate Bundle' : 'Activate SKU')
                        ->modalDescription(function (SellableSku $record): string {
                            if ($record->isComposite()) {
                                $errors = $record->validateCompositeForActivation();
                                if (! empty($errors)) {
                                    return 'Cannot activate: '.implode(' ', $errors);
                                }

                                return 'All component SKUs are active. This bundle can be activated.';
                            }

                            return 'Are you sure you want to activate this SKU?';
                        })
                        ->visible(fn (SellableSku $record): bool => ! $record->isActive())
                        ->disabled(function (SellableSku $record): bool {
                            if ($record->isComposite()) {
                                $errors = $record->validateCompositeForActivation();

                                return ! empty($errors);
                            }

                            return false;
                        })
                        ->action(fn (SellableSku $record) => $this->activateSku($record)),
                    Action::make('retire')
                        ->label('Retire')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Retire SKU')
                        ->modalDescription('Are you sure you want to retire this SKU? It will no longer be available for sale.')
                        ->visible(fn (SellableSku $record): bool => $record->isActive())
                        ->action(fn (SellableSku $record) => $this->retireSku($record)),
                    Action::make('reactivate')
                        ->label('Reactivate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (SellableSku $record): bool => $record->isRetired())
                        ->action(fn (SellableSku $record) => $this->reactivateSku($record)),
                    Action::make('verify')
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
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $activated = 0;
                            $skipped = 0;
                            $errors = [];
                            foreach ($records as $record) {
                                /** @var SellableSku $record */
                                if (! $record->isActive()) {
                                    try {
                                        $record->activate();
                                        $activated++;
                                    } catch (RuntimeException $e) {
                                        $skipped++;
                                        $errors[] = "{$record->sku_code}: {$e->getMessage()}";
                                    }
                                }
                            }

                            if ($activated > 0) {
                                Notification::make()
                                    ->title("{$activated} SKU(s) Activated")
                                    ->body($skipped > 0 ? "{$skipped} skipped due to validation errors" : null)
                                    ->success()
                                    ->send();
                            }

                            if (! empty($errors)) {
                                Notification::make()
                                    ->title('Some SKUs could not be activated')
                                    ->body(implode("\n", array_slice($errors, 0, 3)))
                                    ->warning()
                                    ->send();
                            }
                        }),
                    BulkAction::make('bulk_retire')
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
                    BulkAction::make('bulk_verify')
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
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Sellable SKUs')
            ->emptyStateDescription('Create SKUs to define how this wine can be sold.')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Create SKU')
                    ->icon('heroicon-o-plus'),
                Action::make('generate_intrinsic_empty')
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

    /**
     * Generate SKU code for a composite SKU.
     */
    protected function generateCompositeSkuCode(WineVariant $wineVariant): string
    {
        /** @var WineMaster $wineMaster */
        $wineMaster = $wineVariant->wineMaster;

        // Generate wine code from name (first 4 chars uppercase, alphanumeric only)
        $wineCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $wineMaster->name) ?: 'WINE', 0, 4));

        // Vintage year
        $vintage = $wineVariant->vintage_year;

        // Count existing composite SKUs for this wine variant to create unique suffix
        $existingCount = SellableSku::where('wine_variant_id', $wineVariant->id)
            ->where('is_composite', true)
            ->count();

        $suffix = $existingCount + 1;

        return "{$wineCode}-{$vintage}-BUNDLE-{$suffix}";
    }
}
