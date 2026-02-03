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
