<?php

namespace App\Filament\Resources\PriceBookResource\Pages;

use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PriceSource;
use App\Filament\Resources\PriceBookResource;
use App\Models\Commercial\Channel;
use App\Models\Commercial\PriceBook;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class CreatePriceBook extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PriceBookResource::class;

    /**
     * Track the import method for initial prices.
     */
    public ?string $importMethod = 'empty';

    /**
     * Track the source price book ID for cloning.
     */
    public ?string $sourcePriceBookId = null;

    /**
     * Get the form for creating a price book.
     * Implements a multi-step wizard for price book creation.
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->components([
                Wizard::make($this->getSteps())
                    ->startOnStep($this->getStartStep())
                    ->cancelAction($this->getCancelFormAction())
                    ->submitAction($this->getWizardSubmitAction())
                    ->skippable($this->hasSkippableSteps())
                    ->contained(false),
            ])
            ->columns(null);
    }

    /**
     * Get the wizard submit action (single button: Create as Draft).
     */
    protected function getWizardSubmitAction(): HtmlString
    {
        return new HtmlString(
            Blade::render(<<<'BLADE'
                <x-filament::button
                    type="submit"
                    size="sm"
                >
                    Create as Draft
                </x-filament::button>
            BLADE)
        );
    }

    /**
     * Get the wizard steps.
     *
     * @return array<\Filament\Schemas\Components\Wizard\Step>
     */
    protected function getSteps(): array
    {
        return [
            $this->getMetadataStep(),
            $this->getValidityStep(),
            $this->getInitialPricesStep(),
            $this->getReviewStep(),
        ];
    }

    /**
     * Step 1: Metadata
     * Defines name, market, channel (optional), and currency.
     */
    protected function getMetadataStep(): Step
    {
        return Step::make('Metadata')
            ->description('Define the basic information for this Price Book')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make()
                    ->schema([
                        Placeholder::make('price_book_info')
                            ->label('')
                            ->content('Price Books store base prices for all commercially available SKUs. Each Price Book is scoped to a specific market and currency, and optionally to a sales channel.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Price Book Identity')
                    ->description('Give your price book a clear, descriptive name')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Italy B2C EUR Q1 2026')
                            ->helperText('Use a descriptive name that includes market, channel, and period'),
                    ]),

                Section::make('Market & Currency')
                    ->description('Define the geographic market and pricing currency')
                    ->schema([
                        Select::make('market')
                            ->label('Market')
                            ->required()
                            ->searchable()
                            ->options(function (): array {
                                // Get unique markets from existing price books
                                $existingMarkets = PriceBook::query()
                                    ->distinct()
                                    ->pluck('market', 'market')
                                    ->toArray();

                                // Add common markets
                                $commonMarkets = [
                                    'IT' => 'IT - Italy',
                                    'DE' => 'DE - Germany',
                                    'FR' => 'FR - France',
                                    'UK' => 'UK - United Kingdom',
                                    'US' => 'US - United States',
                                    'CH' => 'CH - Switzerland',
                                    'EU' => 'EU - European Union',
                                    'GLOBAL' => 'GLOBAL - Worldwide',
                                ];

                                return array_merge($commonMarkets, $existingMarkets);
                            })
                            ->allowHtml(false)
                            ->native(false)
                            ->live()
                            ->helperText('Select the geographic market for this price book'),

                        Select::make('currency')
                            ->label('Currency')
                            ->required()
                            ->searchable()
                            ->options([
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                                'USD' => 'USD - US Dollar',
                                'CHF' => 'CHF - Swiss Franc',
                            ])
                            ->native(false)
                            ->live()
                            ->helperText('The currency for all prices in this price book'),
                    ])
                    ->columns(2),

                Section::make('Sales Channel')
                    ->description('Optionally restrict this price book to a specific channel')
                    ->schema([
                        Select::make('channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->helperText('Leave empty to create a channel-agnostic price book that applies to all channels'),

                        Placeholder::make('channel_info')
                            ->label('')
                            ->content(fn (Get $get): string => $get('channel_id') === null
                                ? '**Channel-agnostic:** This price book will apply to all sales channels unless overridden by a channel-specific price book.'
                                : '**Channel-specific:** This price book will only apply to the selected channel.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 2: Validity Period
     * Defines valid_from and valid_to dates with overlap warnings.
     */
    protected function getValidityStep(): Step
    {
        return Step::make('Validity')
            ->description('Define when this Price Book is valid')
            ->icon('heroicon-o-calendar')
            ->schema([
                Section::make('Validity Period')
                    ->description('Set the dates when this price book is effective')
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->required()
                            ->native(false)
                            ->default(now())
                            ->live()
                            ->helperText('The date from which this price book becomes effective'),

                        DatePicker::make('valid_to')
                            ->label('Valid To')
                            ->native(false)
                            ->after('valid_from')
                            ->live()
                            ->helperText('Leave empty for indefinite validity'),

                        Placeholder::make('validity_info')
                            ->label('')
                            ->content(fn (Get $get): string => $get('valid_to') === null
                                ? '**Indefinite validity:** This price book will remain valid until manually archived or replaced.'
                                : '**Fixed period:** This price book will automatically expire after the end date.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Overlap Check')
                    ->description('Check for conflicts with existing price books')
                    ->schema([
                        Placeholder::make('overlap_warning')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $market = $get('market');
                                $channelId = $get('channel_id');
                                $currency = $get('currency');
                                $validFrom = $get('valid_from');
                                $validTo = $get('valid_to');

                                if ($market === null || $currency === null || $validFrom === null) {
                                    return new HtmlString('<span class="text-gray-500">Complete Step 1 (Metadata) to check for overlaps.</span>');
                                }

                                // Find potentially overlapping price books
                                $query = PriceBook::query()
                                    ->where('market', $market)
                                    ->where('currency', $currency)
                                    ->whereIn('status', [PriceBookStatus::Active->value, PriceBookStatus::Draft->value]);

                                if ($channelId !== null) {
                                    $query->where(function ($q) use ($channelId): void {
                                        $q->where('channel_id', $channelId)
                                            ->orWhereNull('channel_id');
                                    });
                                } else {
                                    $query->whereNull('channel_id');
                                }

                                // Check date overlap
                                $query->where(function ($q) use ($validFrom, $validTo): void {
                                    $q->where(function ($inner) use ($validFrom, $validTo): void {
                                        // Existing valid_from is before our valid_to (or our valid_to is null)
                                        $inner->where('valid_from', '<=', $validTo ?? '9999-12-31');
                                        // And existing valid_to is after our valid_from (or existing valid_to is null)
                                        $inner->where(function ($sub) use ($validFrom): void {
                                            $sub->whereNull('valid_to')
                                                ->orWhere('valid_to', '>=', $validFrom);
                                        });
                                    });
                                });

                                $overlappingBooks = $query->get();

                                if ($overlappingBooks->isEmpty()) {
                                    return new HtmlString('<span class="text-green-600 font-medium">✓ No conflicts found. This price book can be created without overlap issues.</span>');
                                }

                                $warnings = [];
                                foreach ($overlappingBooks as $book) {
                                    $channelName = $book->channel !== null ? $book->channel->name : 'All Channels';
                                    $validToDisplay = $book->valid_to !== null ? $book->valid_to->format('Y-m-d') : 'Indefinite';
                                    $statusBadge = $book->status->label();
                                    $warnings[] = "• <strong>{$book->name}</strong> ({$statusBadge}) - {$book->valid_from->format('Y-m-d')} to {$validToDisplay} - Channel: {$channelName}";
                                }

                                $warningHtml = '<div class="text-amber-600">';
                                $warningHtml .= '<p class="font-medium mb-2">⚠️ Potential overlap detected with existing price books:</p>';
                                $warningHtml .= '<ul class="list-none space-y-1">'.implode('', array_map(fn (string $w): string => "<li>{$w}</li>", $warnings)).'</ul>';
                                $warningHtml .= '<p class="mt-2 text-sm">When activated, this price book may need to replace the overlapping ones.</p>';
                                $warningHtml .= '</div>';

                                return new HtmlString($warningHtml);
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Step 3: Initial Prices
     * Optional step for importing initial prices.
     */
    protected function getInitialPricesStep(): Step
    {
        return Step::make('Initial Prices')
            ->description('Optionally import prices during creation')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                Section::make()
                    ->schema([
                        Placeholder::make('prices_info')
                            ->label('')
                            ->content('You can add or modify prices after creation. This step allows you to optionally import initial prices during the creation process.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Import Method')
                    ->description('Choose how to initialize prices')
                    ->schema([
                        Radio::make('import_method')
                            ->label('')
                            ->options([
                                'empty' => 'Empty - Add prices later',
                                'clone' => 'Clone from existing Price Book',
                                'csv' => 'Import from CSV file',
                            ])
                            ->descriptions([
                                'empty' => 'Start with an empty price book and add prices manually or via policies',
                                'clone' => 'Copy all price entries from an existing price book as a starting point',
                                'csv' => 'Upload a CSV file with SKU codes and prices',
                            ])
                            ->default('empty')
                            ->live()
                            ->columnSpanFull(),
                    ]),

                // Clone section - shown when clone is selected
                Section::make('Clone Source')
                    ->description('Select the price book to clone from')
                    ->schema([
                        Select::make('source_price_book_id')
                            ->label('Source Price Book')
                            ->options(function (): array {
                                return PriceBook::query()
                                    ->whereHas('entries')
                                    ->orderByDesc('updated_at')
                                    ->get()
                                    ->mapWithKeys(fn (PriceBook $pb): array => [
                                        $pb->id => "{$pb->name} ({$pb->market} / {$pb->currency}) - {$pb->entries()->count()} entries",
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->helperText('Select a price book with existing entries to clone'),

                        Placeholder::make('clone_preview')
                            ->label('Clone Preview')
                            ->content(function (Get $get): string {
                                $sourceId = $get('source_price_book_id');
                                if ($sourceId === null) {
                                    return 'Select a source price book to see preview';
                                }

                                $source = PriceBook::with('entries')->find($sourceId);
                                if ($source === null) {
                                    return 'Source price book not found';
                                }

                                $entriesCount = $source->entries->count();

                                return "Will clone {$entriesCount} price entries from \"{$source->name}\"";
                            })
                            ->visible(fn (Get $get): bool => $get('source_price_book_id') !== null),
                    ])
                    ->visible(fn (Get $get): bool => $get('import_method') === 'clone'),

                // CSV section - shown when csv is selected
                Section::make('CSV Import')
                    ->description('Upload a CSV file with prices')
                    ->schema([
                        Placeholder::make('csv_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="space-y-2">'.
                                '<p><strong>CSV Format Requirements:</strong></p>'.
                                '<ul class="list-disc list-inside text-sm">'.
                                '<li>Column 1: sellable_sku_id (UUID of the Sellable SKU)</li>'.
                                '<li>Column 2: base_price (decimal value, e.g., 99.99)</li>'.
                                '</ul>'.
                                '<p class="text-sm text-gray-500 mt-2">CSV import will be processed after the price book is created.</p>'.
                                '</div>'
                            ))
                            ->columnSpanFull(),

                        Placeholder::make('csv_not_implemented')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">'.
                                '<p class="text-amber-700">⚠️ CSV import is not yet implemented. Please use "Empty" or "Clone" for now, and add prices manually after creation.</p>'.
                                '</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('import_method') === 'csv'),
            ]);
    }

    /**
     * Step 4: Review & Create
     * Shows a summary and creates as draft.
     */
    protected function getReviewStep(): Step
    {
        return Step::make('Review & Create')
            ->description('Review your price book before creating')
            ->icon('heroicon-o-check-badge')
            ->schema([
                // Draft status info
                Section::make()
                    ->schema([
                        Placeholder::make('draft_info')
                            ->label('')
                            ->content('Draft Price Books are not used for pricing until activated. You can review, add prices, and edit settings before activation.')
                            ->columnSpanFull(),
                    ]),

                // Summary section
                Section::make('Price Book Summary')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Placeholder::make('review_name')
                            ->label('Name')
                            ->content(fn (Get $get): string => $get('name') ?? 'Not set'),

                        Placeholder::make('review_market')
                            ->label('Market')
                            ->content(fn (Get $get): string => $get('market') ?? 'Not set'),

                        Placeholder::make('review_currency')
                            ->label('Currency')
                            ->content(fn (Get $get): string => $get('currency') ?? 'Not set'),

                        Placeholder::make('review_channel')
                            ->label('Channel')
                            ->content(function (Get $get): string {
                                $channelId = $get('channel_id');
                                if ($channelId === null) {
                                    return 'All Channels (channel-agnostic)';
                                }

                                $channel = Channel::find($channelId);

                                return $channel !== null ? $channel->name : 'Unknown';
                            }),
                    ])
                    ->columns(2),

                // Validity section
                Section::make('Validity Period')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Placeholder::make('review_valid_from')
                            ->label('Valid From')
                            ->content(fn (Get $get): string => $get('valid_from') ?? 'Not set'),

                        Placeholder::make('review_valid_to')
                            ->label('Valid To')
                            ->content(fn (Get $get): string => $get('valid_to') ?? 'Indefinite'),
                    ])
                    ->columns(2),

                // Initial prices section
                Section::make('Initial Prices')
                    ->icon('heroicon-o-currency-euro')
                    ->schema([
                        Placeholder::make('review_import_method')
                            ->label('Import Method')
                            ->content(function (Get $get): string {
                                $method = $get('import_method') ?? 'empty';

                                return match ($method) {
                                    'empty' => 'Empty - prices will be added after creation',
                                    'clone' => 'Clone from existing Price Book',
                                    'csv' => 'Import from CSV file',
                                    default => $method,
                                };
                            }),

                        Placeholder::make('review_clone_source')
                            ->label('Clone Source')
                            ->content(function (Get $get): string {
                                $sourceId = $get('source_price_book_id');
                                if ($sourceId === null) {
                                    return 'N/A';
                                }

                                $source = PriceBook::find($sourceId);

                                return $source !== null ? $source->name : 'Unknown';
                            })
                            ->visible(fn (Get $get): bool => $get('import_method') === 'clone'),
                    ]),

                // Status info
                Section::make('Status')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Placeholder::make('status_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="space-y-2">'.
                                '<p><strong>Status: Draft</strong></p>'.
                                '<p class="text-sm text-gray-600">The price book will be created in Draft status. After creation:</p>'.
                                '<ul class="list-disc list-inside text-sm text-gray-600">'.
                                '<li>Add or edit price entries</li>'.
                                '<li>Review all settings</li>'.
                                '<li>Activate when ready to use for pricing</li>'.
                                '</ul>'.
                                '</div>'
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Mutate form data before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set status to draft
        $data['status'] = PriceBookStatus::Draft->value;

        // Store import method data for afterCreate
        if (isset($data['import_method'])) {
            session(['price_book_import_method' => $data['import_method']]);
            session(['price_book_source_id' => $data['source_price_book_id'] ?? null]);
            unset($data['import_method'], $data['source_price_book_id']);
        }

        return $data;
    }

    /**
     * After creating the price book, handle initial prices import.
     */
    protected function afterCreate(): void
    {
        /** @var PriceBook $priceBook */
        $priceBook = $this->record;

        $importMethod = session('price_book_import_method', 'empty');
        $sourceId = session('price_book_source_id');

        session()->forget(['price_book_import_method', 'price_book_source_id']);

        if ($importMethod === 'clone' && $sourceId !== null) {
            $this->cloneEntriesFromSource($priceBook, $sourceId);
        }

        Notification::make()
            ->success()
            ->title('Price Book created')
            ->body("The price book \"{$priceBook->name}\" has been created as Draft. Add prices and activate when ready.")
            ->send();
    }

    /**
     * Clone entries from a source price book.
     */
    protected function cloneEntriesFromSource(PriceBook $target, string $sourceId): void
    {
        $source = PriceBook::with('entries')->find($sourceId);

        if ($source === null) {
            Notification::make()
                ->warning()
                ->title('Clone failed')
                ->body('Source price book not found.')
                ->send();

            return;
        }

        $clonedCount = 0;
        foreach ($source->entries as $entry) {
            $target->entries()->create([
                'sellable_sku_id' => $entry->sellable_sku_id,
                'base_price' => $entry->base_price,
                'source' => PriceSource::Manual->value,
                'policy_id' => null,
            ]);
            $clonedCount++;
        }

        if ($clonedCount > 0) {
            Notification::make()
                ->success()
                ->title('Entries cloned')
                ->body("Cloned {$clonedCount} price entries from \"{$source->name}\".")
                ->send();
        }
    }
}
