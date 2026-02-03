<?php

namespace App\Filament\Resources\Pim;

use App\Enums\ProductLifecycleStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Pim\WineVariantResource\Pages;
use App\Filament\Resources\Pim\WineVariantResource\RelationManagers;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WineVariantResource extends Resource
{
    protected static ?string $model = WineVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Wine Variants';

    protected static ?string $modelLabel = 'Wine Variant';

    protected static ?string $pluralModelLabel = 'Wine Variants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Product Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Core Info')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                self::getCoreInfoSchema(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Attributes')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                self::getAttributesSchema(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    /**
     * Get the Core Info tab schema.
     */
    protected static function getCoreInfoSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            // Liv-ex Status Banner
            Forms\Components\Placeholder::make('livex_status_banner')
                ->label('')
                ->content(function (?WineVariant $record): string {
                    if ($record === null || ! $record->isFromLivEx()) {
                        return '';
                    }
                    $lockedCount = count($record->getLockedFields());

                    return "<div class=\"p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800\">
                        <div class=\"flex items-center gap-2\">
                            <x-heroicon-o-cloud-arrow-down class=\"w-5 h-5 text-blue-600 dark:text-blue-400\" />
                            <span class=\"font-medium text-blue-800 dark:text-blue-200\">Imported from Liv-ex</span>
                        </div>
                        <p class=\"mt-1 text-sm text-blue-700 dark:text-blue-300\">
                            {$lockedCount} fields are locked from Liv-ex data. Manager or Admin can override locked fields.
                        </p>
                    </div>";
                })
                ->visible(fn (?WineVariant $record): bool => $record !== null && $record->isFromLivEx())
                ->dehydrated(false),

            // Identity Section
            Forms\Components\Section::make('Wine Identity')
                ->description('Core identity information for this wine')
                ->icon('heroicon-o-identification')
                ->schema([
                    Forms\Components\Select::make('wine_master_id')
                        ->label('Wine Master')
                        ->relationship('wineMaster', 'name')
                        ->getOptionLabelFromRecordUsing(fn (WineMaster $record): string => "{$record->name} ({$record->producer})")
                        ->searchable(['name', 'producer'])
                        ->preload()
                        ->required()
                        ->disabled(fn (?WineVariant $record): bool => self::isFieldLocked($record, 'wine_master_id'))
                        ->hint(fn (?WineVariant $record): ?string => self::getFieldHint($record, 'wine_master_id'))
                        ->hintIcon(fn (?WineVariant $record): ?string => self::isFieldLocked($record, 'wine_master_id') ? 'heroicon-o-lock-closed' : null)
                        ->hintColor('info'),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Placeholder::make('wine_name_display')
                                ->label('Wine Name')
                                ->content(function (?WineVariant $record): string {
                                    $wineMaster = $record?->wineMaster;

                                    return $wineMaster !== null ? $wineMaster->name : '—';
                                })
                                ->visible(fn (string $operation): bool => $operation === 'edit'),

                            Forms\Components\Placeholder::make('producer_display')
                                ->label('Producer')
                                ->content(function (?WineVariant $record): string {
                                    $wineMaster = $record?->wineMaster;

                                    return $wineMaster !== null ? $wineMaster->producer : '—';
                                })
                                ->visible(fn (string $operation): bool => $operation === 'edit'),

                            Forms\Components\Placeholder::make('appellation_display')
                                ->label('Appellation')
                                ->content(function (?WineVariant $record): string {
                                    $wineMaster = $record?->wineMaster;

                                    return $wineMaster !== null ? ($wineMaster->appellation ?? '—') : '—';
                                })
                                ->visible(fn (string $operation): bool => $operation === 'edit'),

                            Forms\Components\Placeholder::make('region_display')
                                ->label('Region / Country')
                                ->content(function (?WineVariant $record): string {
                                    if ($record?->wineMaster === null) {
                                        return '—';
                                    }
                                    $region = $record->wineMaster->region ?? '';
                                    $country = $record->wineMaster->country ?? '';

                                    return trim("{$region}, {$country}", ', ') ?: '—';
                                })
                                ->visible(fn (string $operation): bool => $operation === 'edit'),
                        ]),

                    Forms\Components\TextInput::make('vintage_year')
                        ->label('Vintage Year')
                        ->required()
                        ->numeric()
                        ->minValue(1800)
                        ->maxValue(date('Y') + 1)
                        ->disabled(fn (?WineVariant $record): bool => self::isFieldLocked($record, 'vintage_year'))
                        ->hint(fn (?WineVariant $record): ?string => self::getFieldHint($record, 'vintage_year'))
                        ->hintIcon(fn (?WineVariant $record): ?string => self::isFieldLocked($record, 'vintage_year') ? 'heroicon-o-lock-closed' : null)
                        ->hintColor('info'),
                ])
                ->columns(1),

            // Internal References Section
            Forms\Components\Section::make('Internal References')
                ->description('Internal codes and identifiers')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('lwin_code')
                                ->label('LWIN Code')
                                ->disabled(fn (?WineVariant $record): bool => self::isFieldLocked($record, 'lwin_code'))
                                ->hint(fn (?WineVariant $record): ?string => self::getFieldHint($record, 'lwin_code'))
                                ->hintIcon(fn (?WineVariant $record): ?string => self::isFieldLocked($record, 'lwin_code') ? 'heroicon-o-lock-closed' : null)
                                ->hintColor('info')
                                ->placeholder('e.g., LWIN1100001'),

                            Forms\Components\TextInput::make('internal_code')
                                ->label('Internal Code')
                                ->placeholder('e.g., CRU-001'),
                        ]),
                ])
                ->collapsible(),

            // Descriptions Section
            Forms\Components\Section::make('Descriptions')
                ->description('Marketing and tasting descriptions')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Wine Description')
                        ->rows(4)
                        ->placeholder('Enter a description for this vintage...')
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('wine_master_description')
                        ->label('Master Description (from Wine Master)')
                        ->content(function (?WineVariant $record): string {
                            $wineMaster = $record?->wineMaster;

                            return $wineMaster !== null && $wineMaster->description !== null
                                ? $wineMaster->description
                                : 'No master description available.';
                        })
                        ->visible(function (?WineVariant $record): bool {
                            $wineMaster = $record?->wineMaster;

                            return $wineMaster !== null && $wineMaster->description !== null;
                        }),
                ])
                ->collapsible(),

            // Override Locked Fields Section (Manager/Admin only)
            Forms\Components\Section::make('Override Locked Fields')
                ->description('As a Manager or Admin, you can override Liv-ex locked fields. This action is audited.')
                ->icon('heroicon-o-lock-open')
                ->schema([
                    Forms\Components\Placeholder::make('override_warning')
                        ->label('')
                        ->content('<div class="p-3 rounded bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 text-warning-800 dark:text-warning-200">
                            <strong>Warning:</strong> Overriding Liv-ex data may cause data inconsistencies. Use with caution.
                        </div>')
                        ->dehydrated(false),

                    Forms\Components\CheckboxList::make('fields_to_unlock')
                        ->label('Select fields to unlock for editing')
                        ->options(fn (?WineVariant $record): array => self::getUnlockableFieldsOptions($record))
                        ->descriptions(fn (?WineVariant $record): array => self::getUnlockableFieldsDescriptions($record))
                        ->visible(fn (?WineVariant $record): bool => $record !== null && count($record->getLockedFields()) > 0)
                        ->dehydrated(false)
                        ->afterStateUpdated(function (?WineVariant $record, $state): void {
                            if ($record !== null && is_array($state)) {
                                foreach ($state as $field) {
                                    $record->unlockField($field);
                                }
                                $record->save();
                                Notification::make()
                                    ->title('Fields Unlocked')
                                    ->body('The selected fields have been unlocked for editing.')
                                    ->success()
                                    ->send();
                            }
                        }),
                ])
                ->visible(fn (?WineVariant $record): bool => $record !== null && $record->isFromLivEx() && self::canOverrideLivEx() && count($record->getLockedFields()) > 0)
                ->collapsed()
                ->collapsible(),
        ]);
    }

    /**
     * Get the Attributes tab schema.
     */
    protected static function getAttributesSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\Section::make('Vintage Information')
                ->description('Technical details specific to this vintage')
                ->icon('heroicon-o-beaker')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('alcohol_percentage')
                                ->label('Alcohol %')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.01)
                                ->suffix('%'),
                        ]),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Drinking Window')
                ->description('Recommended drinking period')
                ->icon('heroicon-o-calendar')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('drinking_window_start')
                                ->label('Start Year')
                                ->numeric()
                                ->minValue(1800)
                                ->maxValue(2200),
                            Forms\Components\TextInput::make('drinking_window_end')
                                ->label('End Year')
                                ->numeric()
                                ->minValue(1800)
                                ->maxValue(2200)
                                ->gte('drinking_window_start'),
                        ]),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Critic Scores')
                ->description('Professional ratings and scores')
                ->icon('heroicon-o-star')
                ->schema([
                    Forms\Components\KeyValue::make('critic_scores')
                        ->label('')
                        ->keyLabel('Critic')
                        ->valueLabel('Score')
                        ->reorderable()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Production Notes')
                ->description('Winemaking and production details')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\KeyValue::make('production_notes')
                        ->label('')
                        ->keyLabel('Note Type')
                        ->valueLabel('Value')
                        ->reorderable()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    /**
     * Check if a field is locked for the given record.
     */
    protected static function isFieldLocked(?WineVariant $record, string $field): bool
    {
        if ($record === null) {
            return false;
        }

        // If user can override Liv-ex fields, they can edit locked fields
        if (self::canOverrideLivEx()) {
            return false;
        }

        return $record->isFieldLocked($field);
    }

    /**
     * Get the hint text for a locked field.
     */
    protected static function getFieldHint(?WineVariant $record, string $field): ?string
    {
        if ($record === null || ! $record->isFieldLocked($field)) {
            return null;
        }

        if (self::canOverrideLivEx()) {
            return 'Liv-ex data (you can override)';
        }

        return 'Locked from Liv-ex';
    }

    /**
     * Check if the current user can override Liv-ex locked fields.
     */
    protected static function canOverrideLivEx(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $role = $user->role;

        return $role !== null && $role->hasAtLeast(UserRole::Manager);
    }

    /**
     * Get unlockable fields as options for the checkbox list.
     *
     * @return array<string, string>
     */
    protected static function getUnlockableFieldsOptions(?WineVariant $record): array
    {
        if ($record === null) {
            return [];
        }

        $lockedFields = $record->getLockedFields();
        $options = [];

        $fieldLabels = [
            'name' => 'Wine Name',
            'producer' => 'Producer',
            'appellation' => 'Appellation',
            'country' => 'Country',
            'region' => 'Region',
            'vintage_year' => 'Vintage Year',
            'lwin_code' => 'LWIN Code',
            'wine_master_id' => 'Wine Master',
        ];

        foreach ($lockedFields as $field) {
            $options[$field] = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        }

        return $options;
    }

    /**
     * Get descriptions for unlockable fields.
     *
     * @return array<string, string>
     */
    protected static function getUnlockableFieldsDescriptions(?WineVariant $record): array
    {
        if ($record === null) {
            return [];
        }

        $lockedFields = $record->getLockedFields();
        $descriptions = [];

        foreach ($lockedFields as $field) {
            $descriptions[$field] = 'Unlock this field to allow editing';
        }

        return $descriptions;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wineMaster.name')
                    ->label('Wine Master')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('wineMaster.producer')
                    ->label('Producer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vintage_year')
                    ->label('Vintage')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('alcohol_percentage')
                    ->label('Alcohol %')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProductLifecycleStatus $state): string => $state->label())
                    ->color(fn (ProductLifecycleStatus $state): string => $state->color())
                    ->icon(fn (ProductLifecycleStatus $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('completeness')
                    ->label('Completeness')
                    ->badge()
                    ->getStateUsing(fn (WineVariant $record): string => $record->getCompletenessPercentage().'%')
                    ->color(fn (WineVariant $record): string => $record->getCompletenessColor())
                    ->sortable(query: function ($query, string $direction): void {
                        // Sort by completeness by ordering by filled fields
                        // This is an approximation since we can't easily sort by computed value
                        $query->orderByRaw('
                            (CASE WHEN alcohol_percentage IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_start IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_end IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 1 ELSE 0 END +
                             CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 1 ELSE 0 END)
                            '.$direction
                        );
                    }),
                Tables\Columns\TextColumn::make('drinking_window_start')
                    ->label('Drink From')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('drinking_window_end')
                    ->label('Drink To')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('wine_master_id')
                    ->label('Wine Master')
                    ->relationship('wineMaster', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options(
                        collect(ProductLifecycleStatus::cases())
                            ->mapWithKeys(fn (ProductLifecycleStatus $status): array => [
                                $status->value => $status->label(),
                            ])
                            ->toArray()
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('submit_for_review')
                        ->label('Submit for Review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Submit for Review')
                        ->modalDescription('Are you sure you want to submit this wine variant for review?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::InReview))
                        ->action(function (WineVariant $record): void {
                            $record->submitForReview();
                            Notification::make()
                                ->title('Submitted for Review')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Wine Variant')
                        ->modalDescription('Are you sure you want to approve this wine variant?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Approved))
                        ->action(function (WineVariant $record): void {
                            $record->approve();
                            Notification::make()
                                ->title('Approved')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject Wine Variant')
                        ->modalDescription('Are you sure you want to reject this wine variant and return it to draft?')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Draft) && $record->isInReview())
                        ->action(function (WineVariant $record): void {
                            $record->reject();
                            Notification::make()
                                ->title('Rejected')
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Publish Wine Variant')
                        ->modalDescription(fn (WineVariant $record): string => $record->hasBlockingIssues()
                            ? 'Cannot publish: there are blocking issues that must be resolved first.'
                            : 'Are you sure you want to publish this wine variant? This will make it visible.')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Published))
                        ->disabled(fn (WineVariant $record): bool => $record->hasBlockingIssues())
                        ->action(function (WineVariant $record): void {
                            if ($record->hasBlockingIssues()) {
                                Notification::make()
                                    ->title('Cannot Publish')
                                    ->body('Resolve all blocking issues before publishing.')
                                    ->danger()
                                    ->send();

                                return;
                            }
                            $record->publish();
                            Notification::make()
                                ->title('Published')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Archive Wine Variant')
                        ->modalDescription('Are you sure you want to archive this wine variant? It will no longer be active.')
                        ->visible(fn (WineVariant $record): bool => $record->canTransitionTo(ProductLifecycleStatus::Archived))
                        ->action(function (WineVariant $record): void {
                            $record->archive();
                            Notification::make()
                                ->title('Archived')
                                ->success()
                                ->send();
                        }),
                ])->label('Lifecycle')
                    ->icon('heroicon-o-arrow-path')
                    ->button(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('vintage_year', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SellableSkusRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWineVariants::route('/'),
            'create' => Pages\CreateWineVariant::route('/create'),
            'view' => Pages\ViewWineVariant::route('/{record}'),
            'edit' => Pages\EditWineVariant::route('/{record}/edit'),
        ];
    }
}
