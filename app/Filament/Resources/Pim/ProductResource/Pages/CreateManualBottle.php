<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\ProductResource;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\Pim\Appellation;
use App\Models\Pim\Country;
use App\Models\Pim\Producer;
use App\Models\Pim\Region;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Page for manually creating a bottle product (Wine Variant).
 *
 * @property \Filament\Schemas\Schema $form
 */
class CreateManualBottle extends Page
{
    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.pim.product-resource.pages.create-manual-bottle';

    protected static ?string $title = 'Create Bottle Product Manually';

    protected static ?string $breadcrumb = 'Manual Creation';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->url(ProductResource::getUrl('create-bottle'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Wine Master')
                    ->description('Select an existing Wine Master or create a new one.')
                    ->schema([
                        Select::make('wine_master_mode')
                            ->label('Wine Master')
                            ->options([
                                'existing' => 'Select Existing Wine Master',
                                'new' => 'Create New Wine Master',
                            ])
                            ->default('existing')
                            ->live()
                            ->required(),

                        Select::make('wine_master_id')
                            ->label('Select Wine Master')
                            ->options(
                                WineMaster::query()
                                    ->with('producerRelation')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (WineMaster $record): array => [
                                        $record->id => $record->name.' - '.$record->producer_name,
                                    ])
                            )
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'existing')
                            ->required(fn (Get $get): bool => $get('wine_master_mode') === 'existing'),

                        // New Wine Master fields
                        TextInput::make('wine_name')
                            ->label('Wine Name')
                            ->placeholder('e.g., Sassicaia')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

                        Select::make('producer_id')
                            ->label('Producer')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->options(
                                Producer::where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Producer $p): array => [$p->id => $p->name])
                                    ->toArray()
                            )
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== null) {
                                    $producer = Producer::find($state);
                                    if ($producer !== null) {
                                        $set('country_id', $producer->country_id);
                                        $set('region_id', $producer->region_id);
                                    }
                                }
                                $set('appellation_id', null);
                            }),

                        Select::make('country_id')
                            ->label('Country')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->options(
                                Country::where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Country $c): array => [$c->id => $c->name])
                                    ->toArray()
                            )
                            ->afterStateUpdated(function (Set $set): void {
                                $set('region_id', null);
                                $set('appellation_id', null);
                            }),

                        Select::make('region_id')
                            ->label('Region')
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->options(function (Get $get): array {
                                $countryId = $get('country_id');
                                if ($countryId === null) {
                                    return [];
                                }

                                return Region::where('is_active', true)
                                    ->where('country_id', $countryId)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function (Region $r): array {
                                        $parent = $r->parentRegion;
                                        $label = $parent !== null
                                            ? $parent->name.' > '.$r->name
                                            : $r->name;

                                        return [$r->id => $label];
                                    })
                                    ->toArray();
                            })
                            ->afterStateUpdated(function (Set $set): void {
                                $set('appellation_id', null);
                            }),

                        Select::make('appellation_id')
                            ->label('Appellation')
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->options(function (Get $get): array {
                                $countryId = $get('country_id');
                                if ($countryId === null) {
                                    return [];
                                }

                                $query = Appellation::where('is_active', true)
                                    ->where('country_id', $countryId);

                                $regionId = $get('region_id');
                                if ($regionId !== null) {
                                    $query->where(function ($q) use ($regionId): void {
                                        $q->where('region_id', $regionId)
                                            ->orWhereNull('region_id');
                                    });
                                }

                                return $query
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Appellation $a): array => [$a->id => $a->name])
                                    ->toArray();
                            }),

                        TextInput::make('classification')
                            ->label('Classification')
                            ->placeholder('e.g., Super Tuscan')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->rows(3),
                    ]),

                Section::make('Wine Variant (Vintage)')
                    ->description('Specify the vintage year and optional details.')
                    ->schema([
                        TextInput::make('vintage_year')
                            ->label('Vintage Year')
                            ->placeholder('e.g., 2019')
                            ->numeric()
                            ->required()
                            ->minValue(1800)
                            ->maxValue(date('Y') + 5),

                        TextInput::make('alcohol_percentage')
                            ->label('Alcohol Percentage')
                            ->placeholder('e.g., 14.5')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),

                        TextInput::make('drinking_window_start')
                            ->label('Drinking Window Start')
                            ->placeholder('e.g., 2025')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2100),

                        TextInput::make('drinking_window_end')
                            ->label('Drinking Window End')
                            ->placeholder('e.g., 2040')
                            ->numeric()
                            ->minValue(1800)
                            ->maxValue(2100),

                        TextInput::make('internal_code')
                            ->label('Internal Code (Optional)')
                            ->placeholder('e.g., SASS-2019')
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Create the Wine Variant and optionally Wine Master.
     */
    public function create(): void
    {
        $data = $this->form->getState();

        // Determine or create Wine Master
        $wineMasterId = null;

        if ($data['wine_master_mode'] === 'existing') {
            $wineMasterId = $data['wine_master_id'];

            if ($wineMasterId === null) {
                Notification::make()
                    ->title('Please select a Wine Master')
                    ->danger()
                    ->send();

                return;
            }
        } else {
            // Look up related records for legacy string fields
            $producer = isset($data['producer_id']) ? Producer::find($data['producer_id']) : null;
            $country = isset($data['country_id']) ? Country::find($data['country_id']) : null;
            $region = isset($data['region_id']) ? Region::find($data['region_id']) : null;
            $appellation = isset($data['appellation_id']) ? Appellation::find($data['appellation_id']) : null;

            // Create new Wine Master with FK IDs and legacy string fields
            $wineMaster = WineMaster::create([
                'name' => $data['wine_name'],
                'producer' => $producer !== null ? $producer->name : '',
                'producer_id' => $data['producer_id'],
                'appellation' => $appellation !== null ? $appellation->name : null,
                'appellation_id' => $data['appellation_id'] ?? null,
                'country' => $country !== null ? $country->name : '',
                'country_id' => $data['country_id'],
                'region' => $region !== null ? $region->name : null,
                'region_id' => $data['region_id'] ?? null,
                'classification' => $data['classification'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $wineMasterId = $wineMaster->id;
        }

        // Check if variant with same vintage exists for this wine master
        $existingVariant = WineVariant::where('wine_master_id', $wineMasterId)
            ->where('vintage_year', $data['vintage_year'])
            ->first();

        if ($existingVariant !== null) {
            $wineMaster = WineMaster::find($wineMasterId);
            $wineName = $wineMaster !== null ? $wineMaster->name : 'this wine';

            Notification::make()
                ->title('Vintage already exists')
                ->body('A '.$data['vintage_year'].' vintage for '.$wineName.' already exists.')
                ->danger()
                ->send();

            return;
        }

        // Create Wine Variant
        $wineVariant = WineVariant::create([
            'wine_master_id' => $wineMasterId,
            'vintage_year' => $data['vintage_year'],
            'alcohol_percentage' => $data['alcohol_percentage'] ?? null,
            'drinking_window_start' => $data['drinking_window_start'] ?? null,
            'drinking_window_end' => $data['drinking_window_end'] ?? null,
            'internal_code' => $data['internal_code'] ?? null,
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'data_source' => DataSource::Manual,
        ]);

        $wineMaster = WineMaster::find($wineMasterId);
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Wine';

        Notification::make()
            ->title('Product created successfully')
            ->body($wineName.' '.$data['vintage_year'].' has been created as a draft product.')
            ->success()
            ->send();

        // Redirect to the edit page for completion
        $this->redirect(WineVariantResource::getUrl('edit', ['record' => $wineVariant]));
    }
}
