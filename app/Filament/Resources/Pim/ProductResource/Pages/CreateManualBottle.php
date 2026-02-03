<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\ProductResource;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

/**
 * Page for manually creating a bottle product (Wine Variant).
 *
 * @property Form $form
 */
class CreateManualBottle extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.pim.product-resource.pages.create-manual-bottle';

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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (WineMaster $record): array => [
                                        $record->id => $record->name.' - '.$record->producer,
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

                        TextInput::make('producer')
                            ->label('Producer')
                            ->placeholder('e.g., Tenuta San Guido')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->required(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

                        TextInput::make('appellation')
                            ->label('Appellation')
                            ->placeholder('e.g., Bolgheri DOC')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

                        TextInput::make('country')
                            ->label('Country')
                            ->placeholder('e.g., Italy')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

                        TextInput::make('region')
                            ->label('Region')
                            ->placeholder('e.g., Tuscany')
                            ->visible(fn (Get $get): bool => $get('wine_master_mode') === 'new')
                            ->maxLength(255),

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
            // Create new Wine Master
            $wineMaster = WineMaster::create([
                'name' => $data['wine_name'],
                'producer' => $data['producer'],
                'appellation' => $data['appellation'] ?? null,
                'country' => $data['country'] ?? null,
                'region' => $data['region'] ?? null,
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
