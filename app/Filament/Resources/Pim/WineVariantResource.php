<?php

namespace App\Filament\Resources\Pim;

use App\Enums\ProductLifecycleStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Pim\WineVariantResource\Pages;
use App\Filament\Resources\Pim\WineVariantResource\RelationManagers;
use App\Models\Pim\ProductMedia;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use App\Services\LivExService;
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
                        Forms\Components\Tabs\Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                self::getMediaSchema(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Sellable SKUs')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                self::getSellableSkusSchema(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Lifecycle')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                self::getLifecycleSchema(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Audit')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                self::getAuditSchema(),
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
        $sections = [];

        // Static sections for core wine variant fields
        $sections[] = Forms\Components\Section::make('Vintage Information')
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
                            ->suffix('%')
                            ->helperText('Optional · Manual'),
                    ]),
            ])
            ->collapsible();

        $sections[] = Forms\Components\Section::make('Drinking Window')
            ->description('Recommended drinking period')
            ->icon('heroicon-o-calendar')
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('drinking_window_start')
                            ->label('Start Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200)
                            ->helperText('Optional · Manual'),
                        Forms\Components\TextInput::make('drinking_window_end')
                            ->label('End Year')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2200)
                            ->gte('drinking_window_start')
                            ->helperText('Optional · Manual'),
                    ]),
            ])
            ->collapsible();

        $sections[] = Forms\Components\Section::make('Critic Scores')
            ->description('Professional ratings and scores')
            ->icon('heroicon-o-star')
            ->schema([
                Forms\Components\KeyValue::make('critic_scores')
                    ->label('')
                    ->keyLabel('Critic')
                    ->valueLabel('Score')
                    ->reorderable()
                    ->columnSpanFull()
                    ->helperText('Optional · Manual'),
            ])
            ->collapsible()
            ->collapsed();

        $sections[] = Forms\Components\Section::make('Production Notes')
            ->description('Winemaking and production details (legacy)')
            ->icon('heroicon-o-document-text')
            ->schema([
                Forms\Components\KeyValue::make('production_notes')
                    ->label('')
                    ->keyLabel('Note Type')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->columnSpanFull()
                    ->helperText('Optional · Manual'),
            ])
            ->collapsible()
            ->collapsed();

        // Dynamic sections from attribute set
        $attributeSet = \App\Models\Pim\AttributeSet::getDefault();
        if ($attributeSet !== null) {
            foreach ($attributeSet->attributeGroups as $group) {
                $fields = self::buildAttributeFieldsForGroup($group);
                if (count($fields) > 0) {
                    $section = Forms\Components\Section::make($group->name)
                        ->description($group->description ?? '')
                        ->icon($group->icon)
                        ->schema($fields)
                        ->collapsible($group->is_collapsible);

                    if ($group->is_collapsed_by_default) {
                        $section->collapsed();
                    }

                    $sections[] = $section;
                }
            }
        }

        // Completeness indicator at the bottom
        $sections[] = Forms\Components\Section::make('Completeness')
            ->description('Track attribute completion progress')
            ->icon('heroicon-o-chart-pie')
            ->schema([
                Forms\Components\Placeholder::make('completeness_indicator')
                    ->label('')
                    ->content(function (?WineVariant $record): string {
                        if ($record === null) {
                            return '<div class="text-gray-500">Save the product to see completeness status.</div>';
                        }
                        $percentage = $record->getDynamicCompletenessPercentage();
                        $color = $record->getCompletenessColor();
                        $colorClass = match ($color) {
                            'danger' => 'text-red-600 dark:text-red-400',
                            'warning' => 'text-yellow-600 dark:text-yellow-400',
                            'success' => 'text-green-600 dark:text-green-400',
                            default => 'text-gray-600 dark:text-gray-400',
                        };
                        $bgClass = match ($color) {
                            'danger' => 'bg-red-500',
                            'warning' => 'bg-yellow-500',
                            'success' => 'bg-green-500',
                            default => 'bg-gray-500',
                        };

                        return "<div class=\"space-y-2\">
                            <div class=\"flex items-center justify-between\">
                                <span class=\"text-sm font-medium {$colorClass}\">Attribute Completeness</span>
                                <span class=\"text-sm font-bold {$colorClass}\">{$percentage}%</span>
                            </div>
                            <div class=\"w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5\">
                                <div class=\"{$bgClass} h-2.5 rounded-full transition-all duration-300\" style=\"width: {$percentage}%\"></div>
                            </div>
                            <p class=\"text-xs text-gray-500 dark:text-gray-400\">
                                Fill in more attributes to improve product completeness. Required fields have higher weight.
                            </p>
                        </div>";
                    })
                    ->dehydrated(false),
            ])
            ->collapsible()
            ->collapsed();

        return Forms\Components\Group::make($sections);
    }

    /**
     * Build form fields for a specific attribute group.
     *
     * @return list<Forms\Components\Component>
     */
    protected static function buildAttributeFieldsForGroup(\App\Models\Pim\AttributeGroup $group): array
    {
        $fields = [];

        foreach ($group->attributeDefinitions as $definition) {
            $field = self::buildAttributeField($definition);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Build a single form field for an attribute definition.
     */
    protected static function buildAttributeField(\App\Models\Pim\AttributeDefinition $definition): ?Forms\Components\Component
    {
        $fieldName = "attributes.{$definition->code}";
        $label = $definition->name;

        // Build helper text with required/optional and source
        $requiredLabel = $definition->is_required ? 'Required' : 'Optional';
        $helperParts = [$requiredLabel];
        if ($definition->unit !== null) {
            $helperParts[] = "Unit: {$definition->unit}";
        }
        $baseHelperText = implode(' · ', $helperParts);

        /** @var 'text'|'textarea'|'number'|'select'|'multiselect'|'boolean'|'date'|'json' $type */
        $type = $definition->type;

        return match ($type) {
            'text' => Forms\Components\TextInput::make($fieldName)
                ->label($label)
                ->placeholder($definition->placeholder)
                ->required($definition->is_required)
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'textarea' => Forms\Components\Textarea::make($fieldName)
                ->label($label)
                ->placeholder($definition->placeholder)
                ->rows(3)
                ->required($definition->is_required)
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'number' => self::buildNumberField($definition, $fieldName, $label, $baseHelperText),

            'select' => Forms\Components\Select::make($fieldName)
                ->label($label)
                ->options(array_combine($definition->options ?? [], $definition->options ?? []))
                ->required($definition->is_required)
                ->searchable()
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'multiselect' => Forms\Components\Select::make($fieldName)
                ->label($label)
                ->options(array_combine($definition->options ?? [], $definition->options ?? []))
                ->multiple()
                ->required($definition->is_required)
                ->searchable()
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'boolean' => Forms\Components\Toggle::make($fieldName)
                ->label($label)
                ->required($definition->is_required)
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'date' => Forms\Components\DatePicker::make($fieldName)
                ->label($label)
                ->required($definition->is_required)
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),

            'json' => Forms\Components\KeyValue::make($fieldName)
                ->label($label)
                ->required($definition->is_required)
                ->reorderable()
                ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
                ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
                ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
                ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
                ->hintColor('info'),
        };
    }

    /**
     * Build a number field with validation rules.
     */
    protected static function buildNumberField(
        \App\Models\Pim\AttributeDefinition $definition,
        string $fieldName,
        string $label,
        string $baseHelperText
    ): Forms\Components\TextInput {
        $field = Forms\Components\TextInput::make($fieldName)
            ->label($label)
            ->numeric()
            ->required($definition->is_required)
            ->helperText(fn (?WineVariant $record) => self::getAttributeHelperText($record, $definition, $baseHelperText))
            ->disabled(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition))
            ->hint(fn (?WineVariant $record) => self::getAttributeLockHint($record, $definition))
            ->hintIcon(fn (?WineVariant $record) => self::isAttributeLocked($record, $definition) ? 'heroicon-o-lock-closed' : null)
            ->hintColor('info');

        /** @var array{min?: int|float, max?: int|float, step?: int|float}|null $rules */
        $rules = $definition->validation_rules;
        if (is_array($rules)) {
            if (isset($rules['min'])) {
                $field->minValue($rules['min']);
            }
            if (isset($rules['max'])) {
                $field->maxValue($rules['max']);
            }
            if (isset($rules['step'])) {
                $field->step($rules['step']);
            }
        }

        if ($definition->unit !== null) {
            $field->suffix($definition->unit);
        }

        return $field;
    }

    /**
     * Get the Media tab schema.
     */
    protected static function getMediaSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            // Liv-ex Media Section (Read-only)
            Forms\Components\Section::make('Liv-ex Media')
                ->description('Images and documents from Liv-ex (read-only)')
                ->icon('heroicon-o-cloud-arrow-down')
                ->schema([
                    Forms\Components\Placeholder::make('livex_media_info')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null || ! $record->isFromLivEx()) {
                                return '<div class="text-gray-500 dark:text-gray-400">This product was not imported from Liv-ex.</div>';
                            }

                            $livExMedia = $record->livExMedia()->get();
                            if ($livExMedia->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No media from Liv-ex.</div>';
                            }

                            $html = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">';
                            foreach ($livExMedia as $media) {
                                $url = $media->getUrl();
                                if ($media->isImage()) {
                                    $html .= '<div class="relative group">';
                                    $html .= '<img src="'.htmlspecialchars((string) $url).'" alt="'.htmlspecialchars((string) $media->alt_text).'" class="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" />';
                                    $html .= '<div class="absolute top-2 right-2">';
                                    $html .= '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">';
                                    $html .= '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>';
                                    $html .= 'Liv-ex';
                                    $html .= '</span>';
                                    $html .= '</div>';
                                    if ($media->is_primary) {
                                        $html .= '<div class="absolute bottom-2 left-2">';
                                        $html .= '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">';
                                        $html .= 'Primary';
                                        $html .= '</span>';
                                        $html .= '</div>';
                                    }
                                    $html .= '</div>';
                                } else {
                                    $html .= '<div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">';
                                    $html .= '<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                                    $html .= '<div class="flex-1 min-w-0">';
                                    $html .= '<p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">'.htmlspecialchars((string) $media->original_filename).'</p>';
                                    $html .= '<p class="text-xs text-gray-500 dark:text-gray-400">'.$media->getFormattedFileSize().'</p>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                            }
                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),

                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('refresh_livex_media')
                            ->label('Refresh Liv-ex Assets')
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->action(function (?WineVariant $record): void {
                                if ($record === null || $record->lwin_code === null) {
                                    Notification::make()
                                        ->title('Cannot Refresh')
                                        ->body('No LWIN code associated with this product.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                // Refresh Liv-ex media
                                $livExService = new LivExService;
                                $wineData = $livExService->getByLwin($record->lwin_code);

                                if ($wineData === null) {
                                    Notification::make()
                                        ->title('No Data Found')
                                        ->body('Could not find wine data in Liv-ex for LWIN: '.$record->lwin_code)
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                // Update Liv-ex image if available
                                $imageUrl = $wineData['image_url'] ?? null;
                                if ($imageUrl !== null) {
                                    // Check if we already have this image
                                    $existingMedia = $record->livExMedia()
                                        ->where('external_url', $imageUrl)
                                        ->first();

                                    if ($existingMedia === null) {
                                        ProductMedia::create([
                                            'wine_variant_id' => $record->id,
                                            'type' => 'image',
                                            'source' => 'liv_ex',
                                            'external_url' => $imageUrl,
                                            'original_filename' => 'liv-ex-image.jpg',
                                            'is_primary' => ! $record->hasPrimaryImage(),
                                            'is_locked' => true,
                                            'sort_order' => 0,
                                        ]);

                                        // Update thumbnail
                                        $record->thumbnail_url = $imageUrl;
                                        $record->saveQuietly();
                                    }
                                }

                                Notification::make()
                                    ->title('Liv-ex Assets Refreshed')
                                    ->success()
                                    ->send();
                            })
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->isFromLivEx()),
                    ]),
                ])
                ->visible(fn (?WineVariant $record): bool => $record !== null && $record->isFromLivEx())
                ->collapsible(),

            // No Liv-ex placeholder
            Forms\Components\Section::make('Liv-ex Media')
                ->description('This product was not imported from Liv-ex')
                ->icon('heroicon-o-cloud-arrow-down')
                ->schema([
                    Forms\Components\Placeholder::make('no_livex_info')
                        ->label('')
                        ->content('<div class="text-gray-500 dark:text-gray-400">This product was created manually and has no Liv-ex media.</div>')
                        ->dehydrated(false),
                ])
                ->visible(fn (?WineVariant $record): bool => $record === null || ! $record->isFromLivEx())
                ->collapsed()
                ->collapsible(),

            // Manual Uploads Section
            Forms\Components\Section::make('Manual Uploads')
                ->description('Upload your own images and documents')
                ->icon('heroicon-o-cloud-arrow-up')
                ->schema([
                    // Primary Image Indicator
                    Forms\Components\Placeholder::make('primary_image_status')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Save the product first to manage media.</p>
                                </div>';
                            }

                            $hasPrimary = $record->hasPrimaryImage();
                            if ($hasPrimary) {
                                return '<div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span class="text-sm font-medium text-green-800 dark:text-green-200">Primary image is set</span>
                                    </div>
                                </div>';
                            }

                            return '<div class="p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">No primary image set - required for publication</span>
                                </div>
                            </div>';
                        })
                        ->dehydrated(false),

                    // Image Upload
                    Forms\Components\FileUpload::make('media_images')
                        ->label('Upload Images')
                        ->disk('public')
                        ->directory('pim/product-media/images')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->maxSize(10240) // 10MB
                        ->maxFiles(20)
                        ->imagePreviewHeight('150')
                        ->panelLayout('grid')
                        ->helperText('Max 20 images, 10MB each. Drag to reorder.')
                        ->visible(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated(false),

                    // Document Upload
                    Forms\Components\FileUpload::make('media_documents')
                        ->label('Upload Documents')
                        ->disk('public')
                        ->directory('pim/product-media/documents')
                        ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->multiple()
                        ->appendFiles()
                        ->maxSize(20480) // 20MB
                        ->maxFiles(10)
                        ->helperText('PDF, DOC, DOCX. Max 10 files, 20MB each.')
                        ->visible(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated(false),

                    // Current Manual Images Display
                    Forms\Components\Placeholder::make('current_manual_images')
                        ->label('Current Images')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '';
                            }

                            $manualImages = $record->manualMedia()->images()->get();
                            if ($manualImages->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No manual images uploaded yet.</div>';
                            }

                            $html = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="manual-images-grid">';
                            foreach ($manualImages as $media) {
                                $url = $media->getUrl();
                                $isPrimary = $media->is_primary;
                                $primaryClass = $isPrimary ? 'ring-2 ring-green-500' : '';

                                $html .= '<div class="relative group '.$primaryClass.' rounded-lg" data-media-id="'.$media->id.'">';
                                $html .= '<img src="'.htmlspecialchars((string) $url).'" alt="'.htmlspecialchars((string) $media->alt_text).'" class="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" />';

                                // Primary badge
                                if ($isPrimary) {
                                    $html .= '<div class="absolute top-2 left-2">';
                                    $html .= '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">';
                                    $html .= '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                                    $html .= 'Primary';
                                    $html .= '</span>';
                                    $html .= '</div>';
                                }

                                // Manual badge
                                $html .= '<div class="absolute top-2 right-2">';
                                $html .= '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">';
                                $html .= 'Manual';
                                $html .= '</span>';
                                $html .= '</div>';

                                $html .= '</div>';
                            }
                            $html .= '</div>';

                            return $html;
                        })
                        ->visible(fn (?WineVariant $record): bool => $record !== null && $record->manualMedia()->images()->count() > 0)
                        ->dehydrated(false),

                    // Media Management Actions
                    Forms\Components\Repeater::make('manual_media_management')
                        ->label('Manage Images')
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\Grid::make(4)
                                ->schema([
                                    Forms\Components\Placeholder::make('preview')
                                        ->label('')
                                        ->content(fn (array $state): string => isset($state['url'])
                                            ? '<img src="'.htmlspecialchars($state['url']).'" class="w-16 h-16 object-cover rounded" />'
                                            : '')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('alt_text')
                                        ->label('Alt Text')
                                        ->placeholder('Image description')
                                        ->columnSpan(2),
                                    Forms\Components\Toggle::make('is_primary')
                                        ->label('Primary')
                                        ->columnSpan(1),
                                ]),
                        ])
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->addable(false)
                        ->deletable(true)
                        ->deleteAction(
                            fn (Forms\Components\Actions\Action $action) => $action
                                ->requiresConfirmation()
                                ->modalHeading('Delete Image')
                                ->modalDescription('Are you sure you want to delete this image? This cannot be undone.')
                        )
                        ->visible(fn (?WineVariant $record): bool => $record !== null && $record->manualMedia()->images()->count() > 0)
                        ->helperText('Drag to reorder. Toggle to set primary image.')
                        ->columnSpanFull(),

                    // Current Manual Documents Display
                    Forms\Components\Placeholder::make('current_manual_documents')
                        ->label('Current Documents')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '';
                            }

                            $manualDocs = $record->manualMedia()->documents()->get();
                            if ($manualDocs->isEmpty()) {
                                return '<div class="text-gray-500 dark:text-gray-400">No documents uploaded yet.</div>';
                            }

                            $html = '<div class="space-y-2">';
                            foreach ($manualDocs as $media) {
                                $url = $media->getUrl();
                                $html .= '<div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">';
                                $html .= '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                                $html .= '<div class="flex-1 min-w-0">';
                                $html .= '<a href="'.htmlspecialchars((string) $url).'" target="_blank" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline truncate block">'.htmlspecialchars((string) $media->original_filename).'</a>';
                                $html .= '<p class="text-xs text-gray-500 dark:text-gray-400">'.$media->getFormattedFileSize().'</p>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';

                            return $html;
                        })
                        ->visible(fn (?WineVariant $record): bool => $record !== null && $record->manualMedia()->documents()->count() > 0)
                        ->dehydrated(false),
                ])
                ->collapsible(),

            // Media Summary
            Forms\Components\Section::make('Media Summary')
                ->description('Overview of all media for this product')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    Forms\Components\Placeholder::make('media_summary')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product to see media summary.</div>';
                            }

                            $livExImages = $record->livExMedia()->images()->count();
                            $livExDocs = $record->livExMedia()->documents()->count();
                            $manualImages = $record->manualMedia()->images()->count();
                            $manualDocs = $record->manualMedia()->documents()->count();
                            $totalImages = $livExImages + $manualImages;
                            $totalDocs = $livExDocs + $manualDocs;
                            $hasPrimary = $record->hasPrimaryImage();

                            $html = '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';

                            // Total Images
                            $html .= '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">';
                            $html .= '<p class="text-2xl font-bold text-blue-600 dark:text-blue-400">'.$totalImages.'</p>';
                            $html .= '<p class="text-sm text-blue-800 dark:text-blue-200">Total Images</p>';
                            $html .= '</div>';

                            // Total Documents
                            $html .= '<div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">';
                            $html .= '<p class="text-2xl font-bold text-purple-600 dark:text-purple-400">'.$totalDocs.'</p>';
                            $html .= '<p class="text-sm text-purple-800 dark:text-purple-200">Documents</p>';
                            $html .= '</div>';

                            // Liv-ex Assets
                            $html .= '<div class="p-4 bg-cyan-50 dark:bg-cyan-900/20 rounded-lg">';
                            $html .= '<p class="text-2xl font-bold text-cyan-600 dark:text-cyan-400">'.($livExImages + $livExDocs).'</p>';
                            $html .= '<p class="text-sm text-cyan-800 dark:text-cyan-200">From Liv-ex</p>';
                            $html .= '</div>';

                            // Primary Image Status
                            $primaryColor = $hasPrimary ? 'green' : 'yellow';
                            $primaryIcon = $hasPrimary ? '✓' : '!';
                            $primaryText = $hasPrimary ? 'Set' : 'Not Set';
                            $html .= '<div class="p-4 bg-'.$primaryColor.'-50 dark:bg-'.$primaryColor.'-900/20 rounded-lg">';
                            $html .= '<p class="text-2xl font-bold text-'.$primaryColor.'-600 dark:text-'.$primaryColor.'-400">'.$primaryIcon.'</p>';
                            $html .= '<p class="text-sm text-'.$primaryColor.'-800 dark:text-'.$primaryColor.'-200">Primary: '.$primaryText.'</p>';
                            $html .= '</div>';

                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    /**
     * Get the Sellable SKUs tab schema.
     */
    protected static function getSellableSkusSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            // SKU Summary Section
            Forms\Components\Section::make('SKU Overview')
                ->description('Summary of sellable SKUs for this product')
                ->icon('heroicon-o-cube')
                ->schema([
                    Forms\Components\Placeholder::make('sku_summary')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to manage SKUs.</div>';
                            }

                            $totalSkus = $record->sellableSkus()->count();
                            $activeSkus = $record->sellableSkus()->where('lifecycle_status', 'active')->count();
                            $draftSkus = $record->sellableSkus()->where('lifecycle_status', 'draft')->count();
                            $retiredSkus = $record->sellableSkus()->where('lifecycle_status', 'retired')->count();
                            $intrinsicSkus = $record->sellableSkus()->where('is_intrinsic', true)->count();
                            $verifiedSkus = $record->sellableSkus()->where('is_verified', true)->count();

                            $html = '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">';

                            // Total SKUs
                            $html .= '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-gray-900 dark:text-gray-100">'.$totalSkus.'</p>';
                            $html .= '<p class="text-xs text-gray-600 dark:text-gray-400">Total SKUs</p>';
                            $html .= '</div>';

                            // Active SKUs
                            $html .= '<div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-green-600 dark:text-green-400">'.$activeSkus.'</p>';
                            $html .= '<p class="text-xs text-green-800 dark:text-green-200">Active</p>';
                            $html .= '</div>';

                            // Draft SKUs
                            $html .= '<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-gray-600 dark:text-gray-400">'.$draftSkus.'</p>';
                            $html .= '<p class="text-xs text-gray-600 dark:text-gray-400">Draft</p>';
                            $html .= '</div>';

                            // Retired SKUs
                            $html .= '<div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-red-600 dark:text-red-400">'.$retiredSkus.'</p>';
                            $html .= '<p class="text-xs text-red-800 dark:text-red-200">Retired</p>';
                            $html .= '</div>';

                            // Intrinsic SKUs
                            $html .= '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-blue-600 dark:text-blue-400">'.$intrinsicSkus.'</p>';
                            $html .= '<p class="text-xs text-blue-800 dark:text-blue-200">Intrinsic</p>';
                            $html .= '</div>';

                            // Verified SKUs
                            $html .= '<div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">';
                            $html .= '<p class="text-2xl font-bold text-purple-600 dark:text-purple-400">'.$verifiedSkus.'</p>';
                            $html .= '<p class="text-xs text-purple-800 dark:text-purple-200">Verified</p>';
                            $html .= '</div>';

                            $html .= '</div>';

                            // Status message
                            if ($totalSkus === 0) {
                                $html .= '<div class="mt-4 p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= '<svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
                                $html .= '<span class="font-medium text-yellow-800 dark:text-yellow-200">At least one SKU is required for publication</span>';
                                $html .= '</div>';
                                $html .= '<p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">Use the SKU management panel below to create SKUs.</p>';
                                $html .= '</div>';
                            } elseif ($activeSkus === 0) {
                                $html .= '<div class="mt-4 p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= '<svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
                                $html .= '<span class="font-medium text-yellow-800 dark:text-yellow-200">No active SKUs</span>';
                                $html .= '</div>';
                                $html .= '<p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">Activate draft SKUs to make them available for sale.</p>';
                                $html .= '</div>';
                            } else {
                                $html .= '<div class="mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= '<svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                $html .= '<span class="font-medium text-green-800 dark:text-green-200">SKUs are properly configured</span>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            return $html;
                        })
                        ->dehydrated(false),
                ]),

            // SKU List Section
            Forms\Components\Section::make('SKU List')
                ->description('Current sellable SKUs for this product. Use the relation manager below for full management.')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Placeholder::make('sku_list')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to see SKUs.</div>';
                            }

                            $skus = $record->sellableSkus()->with(['format', 'caseConfiguration'])->get();

                            if ($skus->isEmpty()) {
                                return '<div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <p class="mt-2">No SKUs defined yet</p>
                                    <p class="text-sm">Scroll down to the SKU management section to create SKUs.</p>
                                </div>';
                            }

                            $html = '<div class="overflow-x-auto">';
                            $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                            $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
                            $html .= '<tr>';
                            $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SKU Code</th>';
                            $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Format</th>';
                            $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Case</th>';
                            $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Flags</th>';
                            $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>';
                            $html .= '</tr>';
                            $html .= '</thead>';
                            $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                            foreach ($skus as $sku) {
                                $format = $sku->format;
                                $caseConfig = $sku->caseConfiguration;

                                /** @var 'owc'|'oc'|'none' $caseTypeValue */
                                $caseTypeValue = $caseConfig->case_type;
                                $caseTypeLabel = match ($caseTypeValue) {
                                    'owc' => 'OWC',
                                    'oc' => 'OC',
                                    'none' => 'Loose',
                                };

                                $statusColor = $sku->getStatusColor();
                                $statusBgClass = match ($statusColor) {
                                    'success' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                    'danger' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                };

                                // Build flags
                                $flags = [];
                                if ($sku->is_intrinsic) {
                                    $flags[] = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Intrinsic</span>';
                                }
                                if ($sku->is_producer_original) {
                                    $flags[] = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">Original</span>';
                                }
                                if ($sku->is_verified) {
                                    $flags[] = '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">✓ Verified</span>';
                                }
                                $flagsHtml = count($flags) > 0 ? implode(' ', $flags) : '<span class="text-gray-400">—</span>';

                                $html .= '<tr>';
                                $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">'.htmlspecialchars($sku->sku_code).'</td>';
                                $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">'.htmlspecialchars($format->name).' ('.$format->volume_ml.'ml)</td>';
                                $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">'.$caseConfig->bottles_per_case.'x '.$caseTypeLabel.'</td>';
                                $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm">'.$flagsHtml.'</td>';
                                $html .= '<td class="px-4 py-2 whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$statusBgClass.'">'.$sku->getStatusLabel().'</span></td>';
                                $html .= '</tr>';
                            }

                            $html .= '</tbody>';
                            $html .= '</table>';
                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),
                ])
                ->collapsible(),

            // Info about RelationManager
            Forms\Components\Section::make('Full SKU Management')
                ->description('Scroll down to the "Sellable SKUs" relation manager for full SKU management including create, edit, delete, activate, retire, and generate intrinsic SKUs.')
                ->icon('heroicon-o-arrow-down')
                ->schema([
                    Forms\Components\Placeholder::make('relation_manager_hint')
                        ->label('')
                        ->content('<div class="text-gray-600 dark:text-gray-400">
                            <p>The full SKU management panel is available below this form. Features include:</p>
                            <ul class="list-disc list-inside mt-2 space-y-1">
                                <li><strong>Create SKU</strong> - Add new SKU with format and case configuration</li>
                                <li><strong>Generate Intrinsic SKUs</strong> - Auto-create standard producer configurations</li>
                                <li><strong>Lifecycle Actions</strong> - Activate, retire, or reactivate SKUs</li>
                                <li><strong>Verify SKUs</strong> - Mark configurations as verified</li>
                                <li><strong>Bulk Actions</strong> - Manage multiple SKUs at once</li>
                            </ul>
                        </div>')
                        ->dehydrated(false),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    /**
     * Get the Lifecycle tab schema.
     */
    protected static function getLifecycleSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            // Current Status Section
            Forms\Components\Section::make('Current Status')
                ->description('View and manage the lifecycle status of this product')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    Forms\Components\Placeholder::make('status_display')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to see lifecycle status.</div>';
                            }

                            $status = $record->lifecycle_status;
                            $statusColor = $status->color();
                            $statusIcon = $status->icon();
                            $statusLabel = $status->label();

                            // Color classes mapping
                            $colorClasses = match ($statusColor) {
                                'gray' => ['bg-gray-100 dark:bg-gray-800', 'text-gray-800 dark:text-gray-200', 'border-gray-300 dark:border-gray-600'],
                                'warning' => ['bg-yellow-100 dark:bg-yellow-900/30', 'text-yellow-800 dark:text-yellow-200', 'border-yellow-300 dark:border-yellow-600'],
                                'info' => ['bg-blue-100 dark:bg-blue-900/30', 'text-blue-800 dark:text-blue-200', 'border-blue-300 dark:border-blue-600'],
                                'success' => ['bg-green-100 dark:bg-green-900/30', 'text-green-800 dark:text-green-200', 'border-green-300 dark:border-green-600'],
                                'danger' => ['bg-red-100 dark:bg-red-900/30', 'text-red-800 dark:text-red-200', 'border-red-300 dark:border-red-600'],
                                default => ['bg-gray-100 dark:bg-gray-800', 'text-gray-800 dark:text-gray-200', 'border-gray-300 dark:border-gray-600'],
                            };

                            $html = '<div class="'.$colorClasses[0].' '.$colorClasses[2].' border rounded-xl p-6">';
                            $html .= '<div class="flex items-center gap-4">';
                            $html .= '<div class="flex-shrink-0">';
                            $html .= '<div class="w-16 h-16 rounded-full '.$colorClasses[0].' flex items-center justify-center">';
                            $html .= '<x-dynamic-component component="'.$statusIcon.'" class="w-8 h-8 '.$colorClasses[1].'" />';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '<div class="flex-1">';
                            $html .= '<h3 class="text-2xl font-bold '.$colorClasses[1].'">'.$statusLabel.'</h3>';
                            $html .= '<p class="text-sm '.$colorClasses[1].' opacity-75 mt-1">'.$status->transitionConfirmation().'</p>';
                            $html .= '</div>';
                            $html .= '</div>';
                            $html .= '</div>';

                            // Show workflow diagram
                            $html .= '<div class="mt-6">';
                            $html .= '<h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Lifecycle Workflow</h4>';
                            $html .= '<div class="flex items-center justify-between gap-2 overflow-x-auto py-2">';

                            $statuses = ProductLifecycleStatus::cases();
                            $currentIndex = array_search($status, $statuses);

                            foreach ($statuses as $index => $s) {
                                $isActive = $s === $status;
                                $isPast = $index < $currentIndex;
                                $sColor = $s->color();

                                $stepBg = $isActive ? match ($sColor) {
                                    'gray' => 'bg-gray-500',
                                    'warning' => 'bg-yellow-500',
                                    'info' => 'bg-blue-500',
                                    'success' => 'bg-green-500',
                                    'danger' => 'bg-red-500',
                                    default => 'bg-gray-500',
                                } : ($isPast ? 'bg-gray-400 dark:bg-gray-600' : 'bg-gray-200 dark:bg-gray-700');

                                $textColor = $isActive ? 'text-white' : ($isPast ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500');
                                $labelColor = $isActive ? 'text-gray-900 dark:text-white font-semibold' : ($isPast ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500');

                                $html .= '<div class="flex flex-col items-center min-w-[80px]">';
                                $html .= '<div class="w-10 h-10 rounded-full '.$stepBg.' flex items-center justify-center '.$textColor.'">';
                                if ($isPast) {
                                    $html .= '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                                } else {
                                    $html .= '<span class="text-sm font-medium">'.($index + 1).'</span>';
                                }
                                $html .= '</div>';
                                $html .= '<span class="text-xs mt-2 '.$labelColor.'">'.$s->label().'</span>';
                                $html .= '</div>';

                                if ($index < count($statuses) - 1) {
                                    $lineColor = $isPast ? 'bg-gray-400 dark:bg-gray-600' : 'bg-gray-200 dark:bg-gray-700';
                                    $html .= '<div class="flex-1 h-0.5 '.$lineColor.' min-w-[20px]"></div>';
                                }
                            }

                            $html .= '</div>';
                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),
                ]),

            // Publish Readiness Section
            Forms\Components\Section::make('Publish Readiness Checklist')
                ->description('Requirements that must be met before publishing')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Forms\Components\Placeholder::make('readiness_checklist')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to see readiness checklist.</div>';
                            }

                            $blockingIssues = $record->getBlockingIssues();
                            $warnings = $record->getWarnings();
                            $canPublish = $record->canPublish();

                            $html = '';

                            // Overall status
                            if ($canPublish) {
                                $html .= '<div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 mb-4">';
                                $html .= '<div class="flex items-center gap-3">';
                                $html .= '<svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                $html .= '<div>';
                                $html .= '<h4 class="font-semibold text-green-800 dark:text-green-200">Ready for Publication</h4>';
                                $html .= '<p class="text-sm text-green-700 dark:text-green-300">All blocking requirements have been met.</p>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            } else {
                                $html .= '<div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 mb-4">';
                                $html .= '<div class="flex items-center gap-3">';
                                $html .= '<svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
                                $html .= '<div>';
                                $html .= '<h4 class="font-semibold text-red-800 dark:text-red-200">Not Ready for Publication</h4>';
                                $html .= '<p class="text-sm text-red-700 dark:text-red-300">'.count($blockingIssues).' blocking issue(s) must be resolved.</p>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            // Blocking Issues
                            if (count($blockingIssues) > 0) {
                                $html .= '<div class="mb-4">';
                                $html .= '<h4 class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2 flex items-center gap-2">';
                                $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                                $html .= 'Blocking Issues ('.count($blockingIssues).')';
                                $html .= '</h4>';
                                $html .= '<ul class="space-y-2">';
                                foreach ($blockingIssues as $issue) {
                                    $tabLabel = match ($issue['tab']) {
                                        'core_info' => 'Core Info',
                                        'attributes' => 'Attributes',
                                        'media' => 'Media',
                                        'sellable_skus' => 'Sellable SKUs',
                                        default => ucfirst(str_replace('_', ' ', $issue['tab'])),
                                    };
                                    $html .= '<li class="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-900/10 rounded-lg">';
                                    $html .= '<svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<p class="text-sm font-medium text-red-800 dark:text-red-200">'.$issue['message'].'</p>';
                                    $html .= '<p class="text-xs text-red-600 dark:text-red-400 mt-1">Go to: <span class="font-medium">'.$tabLabel.' tab</span></p>';
                                    $html .= '</div>';
                                    $html .= '</li>';
                                }
                                $html .= '</ul>';
                                $html .= '</div>';
                            }

                            // Warnings
                            if (count($warnings) > 0) {
                                $html .= '<div>';
                                $html .= '<h4 class="text-sm font-semibold text-yellow-700 dark:text-yellow-300 mb-2 flex items-center gap-2">';
                                $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
                                $html .= 'Warnings ('.count($warnings).')';
                                $html .= '</h4>';
                                $html .= '<ul class="space-y-2">';
                                foreach ($warnings as $warning) {
                                    $tabLabel = match ($warning['tab']) {
                                        'core_info' => 'Core Info',
                                        'attributes' => 'Attributes',
                                        'media' => 'Media',
                                        'sellable_skus' => 'Sellable SKUs',
                                        default => ucfirst(str_replace('_', ' ', $warning['tab'])),
                                    };
                                    $html .= '<li class="flex items-start gap-3 p-3 bg-yellow-50 dark:bg-yellow-900/10 rounded-lg">';
                                    $html .= '<svg class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">'.$warning['message'].'</p>';
                                    $html .= '<p class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">Go to: <span class="font-medium">'.$tabLabel.' tab</span></p>';
                                    $html .= '</div>';
                                    $html .= '</li>';
                                }
                                $html .= '</ul>';
                                $html .= '</div>';
                            }

                            // No issues
                            if (count($blockingIssues) === 0 && count($warnings) === 0) {
                                $html .= '<div class="text-center py-8">';
                                $html .= '<svg class="w-12 h-12 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                                $html .= '<p class="mt-2 text-gray-600 dark:text-gray-400">All requirements met. No issues found.</p>';
                                $html .= '</div>';
                            }

                            return $html;
                        })
                        ->dehydrated(false),
                ])
                ->collapsible(),

            // Lifecycle Actions Section
            Forms\Components\Section::make('Lifecycle Actions')
                ->description('Transition the product through the approval workflow')
                ->icon('heroicon-o-play')
                ->schema([
                    Forms\Components\Placeholder::make('available_actions_info')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to see available actions.</div>';
                            }

                            $allowedTransitions = $record->getAllowedTransitions();

                            if (count($allowedTransitions) === 0) {
                                if ($record->isArchived()) {
                                    return '<div class="p-4 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                            <div>
                                                <h4 class="font-medium text-gray-700 dark:text-gray-300">Product Archived</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">This product has been archived and cannot be modified.</p>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                return '<div class="text-gray-500 dark:text-gray-400">No transitions available from the current status.</div>';
                            }

                            $html = '<div class="space-y-3">';
                            $html .= '<p class="text-sm text-gray-600 dark:text-gray-400 mb-4">The following actions are available based on the current status and your role:</p>';

                            foreach ($allowedTransitions as $targetStatus) {
                                $color = $targetStatus->color();
                                $icon = $targetStatus->icon();
                                $label = $targetStatus->transitionActionLabel();
                                $description = $targetStatus->transitionConfirmation();

                                $bgClass = match ($color) {
                                    'gray' => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                                    'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700',
                                    'info' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700',
                                    'success' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700',
                                    'danger' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700',
                                    default => 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700',
                                };

                                $textClass = match ($color) {
                                    'gray' => 'text-gray-800 dark:text-gray-200',
                                    'warning' => 'text-yellow-800 dark:text-yellow-200',
                                    'info' => 'text-blue-800 dark:text-blue-200',
                                    'success' => 'text-green-800 dark:text-green-200',
                                    'danger' => 'text-red-800 dark:text-red-200',
                                    default => 'text-gray-800 dark:text-gray-200',
                                };

                                $html .= '<div class="p-4 rounded-lg border '.$bgClass.'">';
                                $html .= '<div class="flex items-center justify-between">';
                                $html .= '<div class="flex items-center gap-3">';
                                $html .= '<x-dynamic-component component="'.$icon.'" class="w-6 h-6 '.$textClass.'" />';
                                $html .= '<div>';
                                $html .= '<h4 class="font-medium '.$textClass.'">'.$label.'</h4>';
                                $html .= '<p class="text-sm opacity-75 '.$textClass.'">'.$description.'</p>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '<span class="text-xs px-2 py-1 rounded bg-white/50 dark:bg-black/20 '.$textClass.'">'.$targetStatus->label().'</span>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            $html .= '</div>';
                            $html .= '<p class="text-xs text-gray-500 dark:text-gray-400 mt-4">Use the action buttons in the page header to perform transitions.</p>';

                            return $html;
                        })
                        ->dehydrated(false),

                    // Lifecycle actions
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('lifecycle_submit_for_review')
                            ->label('Submit for Review')
                            ->icon('heroicon-o-paper-airplane')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalHeading('Submit for Review')
                            ->modalDescription('Are you sure you want to submit this product for review?')
                            ->modalSubmitActionLabel('Submit')
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->canTransitionTo(ProductLifecycleStatus::InReview))
                            ->action(function (WineVariant $record): void {
                                $record->submitForReview();
                                Notification::make()
                                    ->title('Submitted for Review')
                                    ->body('The product has been submitted for review.')
                                    ->success()
                                    ->send();
                            }),

                        Forms\Components\Actions\Action::make('lifecycle_approve')
                            ->label('Approve')
                            ->icon('heroicon-o-check')
                            ->color('info')
                            ->requiresConfirmation()
                            ->modalHeading('Approve Product')
                            ->modalDescription('Are you sure you want to approve this product?')
                            ->modalSubmitActionLabel('Approve')
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->canTransitionTo(ProductLifecycleStatus::Approved) && self::canApproveOrReject())
                            ->action(function (WineVariant $record): void {
                                $record->approve();
                                Notification::make()
                                    ->title('Product Approved')
                                    ->body('The product has been approved and is ready for publication.')
                                    ->success()
                                    ->send();
                            }),

                        Forms\Components\Actions\Action::make('lifecycle_reject')
                            ->label('Reject')
                            ->icon('heroicon-o-x-mark')
                            ->color('danger')
                            ->form([
                                Forms\Components\Textarea::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->helperText('Please provide a reason for rejecting this product. This will be recorded in the audit log.')
                                    ->required()
                                    ->rows(3)
                                    ->placeholder('Enter the reason for rejection...'),
                            ])
                            ->modalHeading('Reject Product')
                            ->modalDescription('Please provide a reason for rejecting this product.')
                            ->modalSubmitActionLabel('Reject')
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->canTransitionTo(ProductLifecycleStatus::Draft) && $record->isInReview() && self::canApproveOrReject())
                            ->action(function (WineVariant $record, array $data): void {
                                // Store rejection reason in audit log
                                \App\Models\AuditLog::create([
                                    'auditable_type' => WineVariant::class,
                                    'auditable_id' => $record->id,
                                    'event' => 'status_change',
                                    'old_values' => ['lifecycle_status' => $record->lifecycle_status->value],
                                    'new_values' => [
                                        'lifecycle_status' => ProductLifecycleStatus::Draft->value,
                                        'rejection_reason' => $data['rejection_reason'],
                                    ],
                                    'user_id' => Auth::id(),
                                ]);

                                $record->lifecycle_status = ProductLifecycleStatus::Draft;
                                $record->saveQuietly(); // Use saveQuietly to avoid duplicate audit logs

                                Notification::make()
                                    ->title('Product Rejected')
                                    ->body('The product has been rejected and returned to draft status.')
                                    ->warning()
                                    ->send();
                            }),

                        Forms\Components\Actions\Action::make('lifecycle_publish')
                            ->label('Publish')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Publish Product')
                            ->modalDescription(fn (?WineVariant $record): string => $record !== null && $record->hasBlockingIssues()
                                ? 'Cannot publish: there are blocking issues that must be resolved first.'
                                : 'Are you sure you want to publish this product? This will make it visible to customers.')
                            ->modalSubmitActionLabel('Publish')
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->canTransitionTo(ProductLifecycleStatus::Published) && self::canPublish())
                            ->disabled(fn (?WineVariant $record): bool => $record !== null && $record->hasBlockingIssues())
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
                                    ->title('Product Published')
                                    ->body('The product is now live and visible to customers.')
                                    ->success()
                                    ->send();
                            }),

                        Forms\Components\Actions\Action::make('lifecycle_archive')
                            ->label('Archive')
                            ->icon('heroicon-o-archive-box')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Archive Product')
                            ->modalDescription('Are you sure you want to archive this product? It will no longer be active or visible.')
                            ->modalSubmitActionLabel('Archive')
                            ->visible(fn (?WineVariant $record): bool => $record !== null && $record->canTransitionTo(ProductLifecycleStatus::Archived) && self::canArchive())
                            ->action(function (WineVariant $record): void {
                                $record->archive();
                                Notification::make()
                                    ->title('Product Archived')
                                    ->body('The product has been archived.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                        ->visible(fn (?WineVariant $record): bool => $record !== null && count($record->getAllowedTransitions()) > 0)
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            // Recent Status History
            Forms\Components\Section::make('Recent Status Changes')
                ->description('Recent lifecycle status changes for this product')
                ->icon('heroicon-o-clock')
                ->schema([
                    Forms\Components\Placeholder::make('recent_status_history')
                        ->label('')
                        ->content(function (?WineVariant $record): string {
                            if ($record === null) {
                                return '<div class="text-gray-500 dark:text-gray-400">Save the product first to see status history.</div>';
                            }

                            $statusChanges = \App\Models\AuditLog::where('auditable_type', WineVariant::class)
                                ->where('auditable_id', $record->id)
                                ->where('event', 'status_change')
                                ->with('user')
                                ->orderByDesc('created_at')
                                ->limit(5)
                                ->get();

                            if ($statusChanges->isEmpty()) {
                                return '<div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="mt-2">No status changes recorded yet.</p>
                                </div>';
                            }

                            $html = '<div class="space-y-4">';

                            foreach ($statusChanges as $log) {
                                /** @var array<string, mixed>|null $rawOldValues */
                                $rawOldValues = $log->old_values;
                                /** @var array<string, mixed> $oldValues */
                                $oldValues = $rawOldValues ?? [];
                                /** @var array<string, mixed>|null $rawNewValues */
                                $rawNewValues = $log->new_values;
                                /** @var array<string, mixed> $newValues */
                                $newValues = $rawNewValues ?? [];

                                $oldStatusValue = isset($oldValues['lifecycle_status']) && is_string($oldValues['lifecycle_status'])
                                    ? $oldValues['lifecycle_status']
                                    : null;
                                $newStatusValue = isset($newValues['lifecycle_status']) && is_string($newValues['lifecycle_status'])
                                    ? $newValues['lifecycle_status']
                                    : null;

                                $oldStatus = $oldStatusValue !== null ? ProductLifecycleStatus::tryFrom($oldStatusValue) : null;
                                $newStatus = $newStatusValue !== null ? ProductLifecycleStatus::tryFrom($newStatusValue) : null;

                                $oldLabel = $oldStatus !== null ? $oldStatus->label() : 'Unknown';
                                $newLabel = $newStatus !== null ? $newStatus->label() : 'Unknown';

                                $newColor = $newStatus !== null ? $newStatus->color() : 'gray';
                                /** @var 'gray'|'warning'|'info'|'success'|'danger' $newColor */
                                $colorClass = match ($newColor) {
                                    'gray' => 'border-l-gray-400',
                                    'warning' => 'border-l-yellow-400',
                                    'info' => 'border-l-blue-400',
                                    'success' => 'border-l-green-400',
                                    'danger' => 'border-l-red-400',
                                };

                                $user = $log->user;
                                $userName = $user !== null ? $user->name : 'System';
                                $time = $log->created_at->diffForHumans();
                                $fullTime = $log->created_at->format('M d, Y H:i');

                                $html .= '<div class="border-l-4 '.$colorClass.' pl-4 py-2">';
                                $html .= '<div class="flex items-center justify-between">';
                                $html .= '<div>';
                                $html .= '<p class="font-medium text-gray-900 dark:text-gray-100">'.$oldLabel.' → '.$newLabel.'</p>';

                                // Show rejection reason if present
                                $rejectionReason = isset($newValues['rejection_reason']) && is_string($newValues['rejection_reason'])
                                    ? $newValues['rejection_reason']
                                    : null;
                                if ($rejectionReason !== null) {
                                    $html .= '<p class="text-sm text-red-600 dark:text-red-400 mt-1">';
                                    $html .= '<span class="font-medium">Reason:</span> '.htmlspecialchars($rejectionReason);
                                    $html .= '</p>';
                                }

                                $html .= '<p class="text-sm text-gray-600 dark:text-gray-400">by '.$userName.'</p>';
                                $html .= '</div>';
                                $html .= '<span class="text-xs text-gray-500 dark:text-gray-400" title="'.$fullTime.'">'.$time.'</span>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    /**
     * Get the Audit tab schema.
     */
    protected static function getAuditSchema(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            // Audit Log Header
            Forms\Components\Section::make('Audit History')
                ->description('Complete timeline of all changes made to this product')
                ->icon('heroicon-o-clock')
                ->schema([
                    // Filter Controls
                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('audit_event_filter')
                                ->label('Event Type')
                                ->options([
                                    '' => 'All Events',
                                    \App\Models\AuditLog::EVENT_CREATED => 'Created',
                                    \App\Models\AuditLog::EVENT_UPDATED => 'Updated',
                                    \App\Models\AuditLog::EVENT_DELETED => 'Deleted',
                                    \App\Models\AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                                ])
                                ->default('')
                                ->live()
                                ->dehydrated(false),
                            Forms\Components\DatePicker::make('audit_date_from')
                                ->label('From Date')
                                ->live()
                                ->dehydrated(false),
                            Forms\Components\DatePicker::make('audit_date_to')
                                ->label('To Date')
                                ->live()
                                ->dehydrated(false),
                        ]),

                    // Timeline Display
                    Forms\Components\Placeholder::make('audit_timeline')
                        ->label('')
                        ->content(function (?WineVariant $record, Forms\Get $get): string {
                            if ($record === null) {
                                return '<div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                    <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="mt-4 text-lg">Save the product first to see audit history.</p>
                                </div>';
                            }

                            // Build query with filters
                            $query = \App\Models\AuditLog::where('auditable_type', WineVariant::class)
                                ->where('auditable_id', $record->id)
                                ->with('user')
                                ->orderByDesc('created_at');

                            // Apply event type filter
                            /** @var string|null $eventFilter */
                            $eventFilter = $get('audit_event_filter');
                            if ($eventFilter !== null && $eventFilter !== '') {
                                $query->where('event', $eventFilter);
                            }

                            // Apply date from filter
                            /** @var string|null $dateFrom */
                            $dateFrom = $get('audit_date_from');
                            if ($dateFrom !== null && $dateFrom !== '') {
                                $query->whereDate('created_at', '>=', $dateFrom);
                            }

                            // Apply date to filter
                            /** @var string|null $dateTo */
                            $dateTo = $get('audit_date_to');
                            if ($dateTo !== null && $dateTo !== '') {
                                $query->whereDate('created_at', '<=', $dateTo);
                            }

                            $auditLogs = $query->get();

                            if ($auditLogs->isEmpty()) {
                                return '<div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                    <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <p class="mt-4 text-lg">No audit events found matching your filters.</p>
                                </div>';
                            }

                            // Build timeline HTML
                            $html = '<div class="relative">';

                            // Timeline line
                            $html .= '<div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>';

                            $html .= '<div class="space-y-6">';

                            foreach ($auditLogs as $log) {
                                /** @var \App\Models\AuditLog $log */
                                $eventLabel = $log->getEventLabel();
                                $eventIcon = $log->getEventIcon();
                                $eventColor = $log->getEventColor();

                                // Color classes for the event badge
                                $colorClasses = match ($eventColor) {
                                    'success' => ['bg-green-100 dark:bg-green-900/30', 'text-green-800 dark:text-green-200', 'border-green-500', 'bg-green-500'],
                                    'info' => ['bg-blue-100 dark:bg-blue-900/30', 'text-blue-800 dark:text-blue-200', 'border-blue-500', 'bg-blue-500'],
                                    'warning' => ['bg-yellow-100 dark:bg-yellow-900/30', 'text-yellow-800 dark:text-yellow-200', 'border-yellow-500', 'bg-yellow-500'],
                                    'danger' => ['bg-red-100 dark:bg-red-900/30', 'text-red-800 dark:text-red-200', 'border-red-500', 'bg-red-500'],
                                    default => ['bg-gray-100 dark:bg-gray-800', 'text-gray-800 dark:text-gray-200', 'border-gray-500', 'bg-gray-500'],
                                };

                                $user = $log->user;
                                $userName = $user !== null ? htmlspecialchars($user->name) : 'System';
                                $userEmail = $user !== null ? htmlspecialchars($user->email) : '';
                                $createdAt = $log->created_at;
                                $timeAgo = $createdAt !== null ? $createdAt->diffForHumans() : 'Unknown';
                                $fullTime = $createdAt !== null ? $createdAt->format('M d, Y \\a\\t H:i:s') : 'Unknown';

                                $html .= '<div class="relative flex items-start gap-4 pl-10">';

                                // Timeline dot
                                $html .= '<div class="absolute left-2 w-5 h-5 rounded-full '.$colorClasses[3].' border-4 border-white dark:border-gray-900 -translate-x-1/2"></div>';

                                // Event card
                                $html .= '<div class="flex-1 '.$colorClasses[0].' rounded-lg border '.$colorClasses[2].' p-4">';

                                // Header row
                                $html .= '<div class="flex items-center justify-between mb-3">';
                                $html .= '<div class="flex items-center gap-3">';
                                $html .= '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium '.$colorClasses[0].' '.$colorClasses[1].' border '.$colorClasses[2].'">';
                                $html .= '<x-dynamic-component component="'.$eventIcon.'" class="w-4 h-4" />';
                                $html .= $eventLabel;
                                $html .= '</span>';
                                $html .= '</div>';
                                $html .= '<span class="text-sm text-gray-500 dark:text-gray-400" title="'.$fullTime.'">'.$timeAgo.'</span>';
                                $html .= '</div>';

                                // User info
                                $html .= '<div class="flex items-center gap-2 mb-3 text-sm text-gray-600 dark:text-gray-400">';
                                $html .= '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
                                $html .= '<span><strong>'.$userName.'</strong>';
                                if ($userEmail !== '') {
                                    $html .= ' <span class="text-gray-400">('.$userEmail.')</span>';
                                }
                                $html .= '</span>';
                                $html .= '</div>';

                                // Changes detail
                                $html .= '<div class="text-sm">';
                                $html .= self::formatAuditLogChanges($log);
                                $html .= '</div>';

                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            $html .= '</div>';
                            $html .= '</div>';

                            // Summary footer
                            $html .= '<div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">';
                            $html .= '<p class="text-sm text-gray-500 dark:text-gray-400 text-center">';
                            $html .= 'Showing '.$auditLogs->count().' audit event(s)';
                            $html .= '</p>';
                            $html .= '</div>';

                            return $html;
                        })
                        ->dehydrated(false),
                ]),
        ]);
    }

    /**
     * Format audit log changes for detailed display in the Audit tab.
     */
    protected static function formatAuditLogChanges(\App\Models\AuditLog $log): string
    {
        /** @var array<string, mixed>|null $rawOldValues */
        $rawOldValues = $log->old_values;
        /** @var array<string, mixed> $oldValues */
        $oldValues = $rawOldValues ?? [];

        /** @var array<string, mixed>|null $rawNewValues */
        $rawNewValues = $log->new_values;
        /** @var array<string, mixed> $newValues */
        $newValues = $rawNewValues ?? [];

        if ($log->event === \App\Models\AuditLog::EVENT_CREATED) {
            $fieldCount = count($newValues);
            $html = '<div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">';
            $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2">Record created with '.$fieldCount.' field(s) set:</p>';

            if ($fieldCount > 0 && $fieldCount <= 10) {
                $html .= '<ul class="list-disc list-inside space-y-1 text-gray-700 dark:text-gray-300">';
                foreach ($newValues as $field => $value) {
                    $fieldLabel = self::formatFieldName($field);
                    $valueDisplay = self::formatFieldValue($value);
                    $html .= '<li><strong>'.$fieldLabel.'</strong>: '.$valueDisplay.'</li>';
                }
                $html .= '</ul>';
            } elseif ($fieldCount > 10) {
                $html .= '<p class="text-gray-500 dark:text-gray-400 italic">('.$fieldCount.' fields - expand to see all)</p>';
            }

            $html .= '</div>';

            return $html;
        }

        if ($log->event === \App\Models\AuditLog::EVENT_DELETED) {
            return '<div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                <p class="text-red-700 dark:text-red-300">Record was permanently deleted.</p>
            </div>';
        }

        if ($log->event === \App\Models\AuditLog::EVENT_STATUS_CHANGE) {
            $oldStatusValue = isset($oldValues['lifecycle_status']) && is_string($oldValues['lifecycle_status'])
                ? $oldValues['lifecycle_status']
                : null;
            $newStatusValue = isset($newValues['lifecycle_status']) && is_string($newValues['lifecycle_status'])
                ? $newValues['lifecycle_status']
                : null;

            $oldStatus = $oldStatusValue !== null ? ProductLifecycleStatus::tryFrom($oldStatusValue) : null;
            $newStatus = $newStatusValue !== null ? ProductLifecycleStatus::tryFrom($newStatusValue) : null;

            $oldLabel = $oldStatus !== null ? $oldStatus->label() : 'Unknown';
            $newLabel = $newStatus !== null ? $newStatus->label() : 'Unknown';

            $html = '<div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">';
            $html .= '<p class="text-gray-700 dark:text-gray-300">';
            $html .= 'Status changed from <strong class="text-gray-900 dark:text-white">'.$oldLabel.'</strong>';
            $html .= ' to <strong class="text-gray-900 dark:text-white">'.$newLabel.'</strong>';
            $html .= '</p>';

            // Show rejection reason if present
            $rejectionReason = isset($newValues['rejection_reason']) && is_string($newValues['rejection_reason'])
                ? $newValues['rejection_reason']
                : null;

            if ($rejectionReason !== null && $rejectionReason !== '') {
                $html .= '<div class="mt-2 p-2 bg-red-100 dark:bg-red-900/30 rounded">';
                $html .= '<p class="text-red-700 dark:text-red-300 text-sm">';
                $html .= '<strong>Rejection reason:</strong> '.htmlspecialchars($rejectionReason);
                $html .= '</p>';
                $html .= '</div>';
            }

            $html .= '</div>';

            return $html;
        }

        // EVENT_UPDATED - show field changes
        $changes = [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if (count($changes) === 0) {
            return '<div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">
                <p class="text-gray-500 dark:text-gray-400 italic">No field changes recorded.</p>
            </div>';
        }

        $html = '<div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-3">';
        $html .= '<p class="text-gray-600 dark:text-gray-400 mb-2">'.count($changes).' field(s) changed:</p>';
        $html .= '<div class="space-y-2">';

        foreach ($changes as $change) {
            $fieldLabel = self::formatFieldName($change['field']);
            $oldDisplay = self::formatFieldValue($change['old']);
            $newDisplay = self::formatFieldValue($change['new']);

            $html .= '<div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 py-1 border-b border-gray-200 dark:border-gray-700 last:border-0">';
            $html .= '<span class="font-medium text-gray-700 dark:text-gray-300 min-w-[120px]">'.$fieldLabel.':</span>';
            $html .= '<span class="text-red-600 dark:text-red-400 line-through">'.$oldDisplay.'</span>';
            $html .= '<span class="text-gray-400 mx-1">→</span>';
            $html .= '<span class="text-green-600 dark:text-green-400">'.$newDisplay.'</span>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Format a field name for display.
     */
    protected static function formatFieldName(string $field): string
    {
        // Handle common field name transformations
        $fieldMappings = [
            'wine_master_id' => 'Wine Master',
            'vintage_year' => 'Vintage Year',
            'lifecycle_status' => 'Lifecycle Status',
            'alcohol_percentage' => 'Alcohol %',
            'drinking_window_start' => 'Drinking Window Start',
            'drinking_window_end' => 'Drinking Window End',
            'critic_scores' => 'Critic Scores',
            'production_notes' => 'Production Notes',
            'lwin_code' => 'LWIN Code',
            'internal_code' => 'Internal Code',
            'data_source' => 'Data Source',
            'thumbnail_url' => 'Thumbnail URL',
            'locked_fields' => 'Locked Fields',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
        ];

        if (isset($fieldMappings[$field])) {
            return $fieldMappings[$field];
        }

        // Default: convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Format a field value for display.
     */
    protected static function formatFieldValue(mixed $value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">empty</em>';
        }

        if (is_bool($value)) {
            return $value ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>';
        }

        if (is_array($value)) {
            if (count($value) === 0) {
                return '<em class="text-gray-400">empty array</em>';
            }

            return '<em class="text-gray-500">['.count($value).' item(s)]</em>';
        }

        if (is_object($value)) {
            return '<em class="text-gray-500">[object]</em>';
        }

        if (! is_scalar($value)) {
            return '<em class="text-gray-400">unknown type</em>';
        }

        $stringValue = (string) $value;

        if (strlen($stringValue) > 100) {
            return htmlspecialchars(substr($stringValue, 0, 97)).'...';
        }

        return htmlspecialchars($stringValue);
    }

    /**
     * Check if the current user can approve or reject products.
     */
    protected static function canApproveOrReject(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $role = $user->role;

        // Managers and above can approve/reject
        return $role !== null && $role->hasAtLeast(UserRole::Manager);
    }

    /**
     * Check if the current user can publish products.
     */
    protected static function canPublish(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $role = $user->role;

        // Managers and above can publish
        return $role !== null && $role->hasAtLeast(UserRole::Manager);
    }

    /**
     * Check if the current user can archive products.
     */
    protected static function canArchive(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $role = $user->role;

        // Admins and above can archive
        return $role !== null && $role->hasAtLeast(UserRole::Admin);
    }

    /**
     * Get helper text for an attribute, including source info.
     */
    protected static function getAttributeHelperText(?WineVariant $record, \App\Models\Pim\AttributeDefinition $definition, string $baseText): string
    {
        if ($record === null) {
            return $baseText.' · Manual';
        }

        $attrValue = $record->attributeValues()
            ->where('attribute_definition_id', $definition->id)
            ->first();

        $source = 'Manual';
        if ($attrValue !== null) {
            $source = $attrValue->source === \App\Enums\DataSource::LivEx ? 'Liv-ex' : 'Manual';
        }

        $helpText = $definition->help_text;
        $parts = [$baseText, "Source: {$source}"];
        if ($helpText !== null && $helpText !== '') {
            $parts[] = $helpText;
        }

        return implode(' · ', $parts);
    }

    /**
     * Check if an attribute is locked (from Liv-ex).
     */
    protected static function isAttributeLocked(?WineVariant $record, \App\Models\Pim\AttributeDefinition $definition): bool
    {
        if ($record === null) {
            return false;
        }

        // Managers and Admins can override
        if (self::canOverrideLivEx()) {
            return false;
        }

        $attrValue = $record->attributeValues()
            ->where('attribute_definition_id', $definition->id)
            ->first();

        return $attrValue !== null && $attrValue->is_locked;
    }

    /**
     * Get lock hint for an attribute.
     */
    protected static function getAttributeLockHint(?WineVariant $record, \App\Models\Pim\AttributeDefinition $definition): ?string
    {
        if ($record === null) {
            return null;
        }

        $attrValue = $record->attributeValues()
            ->where('attribute_definition_id', $definition->id)
            ->first();

        if ($attrValue === null || ! $attrValue->is_locked) {
            return null;
        }

        if (self::canOverrideLivEx()) {
            return 'Liv-ex data (you can override)';
        }

        return 'Locked from Liv-ex';
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
