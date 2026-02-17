<?php

namespace App\Filament\Resources\Pim\ProductResource\Pages;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\ProductResource;
use App\Filament\Resources\Pim\WineVariantResource;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Services\LivExService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Page for searching and importing wine data from Liv-ex.
 */
class ImportLivex extends Page
{
    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.pim.product-resource.pages.import-livex';

    protected static ?string $title = 'Import from Liv-ex';

    protected static ?string $breadcrumb = 'Import Liv-ex';

    #[Url]
    public string $searchQuery = '';

    public ?string $selectedLwin = null;

    /**
     * @var array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}|null
     */
    public ?array $selectedWine = null;

    public bool $showConfirmation = false;

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

    /**
     * Get search results from Liv-ex.
     *
     * @return list<array{lwin: string, name: string, producer: string, vintage: int, appellation: string, country: string, region: string, classification: string|null, alcohol: float|null, drinking_window_start: int|null, drinking_window_end: int|null, description: string|null, image_url: string|null}>
     */
    #[Computed]
    public function searchResults(): array
    {
        if (Str::length($this->searchQuery) < 2) {
            return [];
        }

        $service = new LivExService;

        return $service->search($this->searchQuery);
    }

    /**
     * Select a wine from search results.
     */
    public function selectWine(string $lwin): void
    {
        $service = new LivExService;
        $wine = $service->getByLwin($lwin);

        if ($wine === null) {
            Notification::make()
                ->title('Wine not found')
                ->danger()
                ->send();

            return;
        }

        $this->selectedLwin = $lwin;
        $this->selectedWine = $wine;
        $this->showConfirmation = true;
    }

    /**
     * Cancel wine selection.
     */
    public function cancelSelection(): void
    {
        $this->selectedLwin = null;
        $this->selectedWine = null;
        $this->showConfirmation = false;
    }

    /**
     * Import the selected wine and create Wine Master/Variant.
     */
    public function confirmImport(): void
    {
        if ($this->selectedWine === null) {
            Notification::make()
                ->title('No wine selected')
                ->danger()
                ->send();

            return;
        }

        $wine = $this->selectedWine;

        // Check if a variant with this LWIN already exists
        $existingVariant = WineVariant::where('lwin_code', $wine['lwin'])->first();
        if ($existingVariant !== null) {
            Notification::make()
                ->title('Wine already exists')
                ->body('A wine variant with LWIN '.$wine['lwin'].' already exists in the system.')
                ->danger()
                ->send();

            return;
        }

        // Find or create Wine Master
        $wineMaster = WineMaster::where('name', $wine['name'])
            ->where('producer', $wine['producer'])
            ->first();

        if ($wineMaster === null) {
            $wineMaster = WineMaster::create([
                'name' => $wine['name'],
                'producer' => $wine['producer'],
                'appellation' => $wine['appellation'],
                'classification' => $wine['classification'],
                'country' => $wine['country'],
                'region' => $wine['region'],
                'description' => $wine['description'],
                'liv_ex_code' => Str::before($wine['lwin'], '-'),
            ]);
        }

        // Check if variant with same vintage exists for this wine master
        $existingVintage = WineVariant::where('wine_master_id', $wineMaster->id)
            ->where('vintage_year', $wine['vintage'])
            ->first();

        if ($existingVintage !== null) {
            Notification::make()
                ->title('Vintage already exists')
                ->body('A '.$wine['vintage'].' vintage for '.$wine['name'].' already exists.')
                ->danger()
                ->send();

            return;
        }

        // Create Wine Variant with locked fields from Liv-ex
        $wineVariant = WineVariant::create([
            'wine_master_id' => $wineMaster->id,
            'vintage_year' => $wine['vintage'],
            'alcohol_percentage' => $wine['alcohol'],
            'drinking_window_start' => $wine['drinking_window_start'],
            'drinking_window_end' => $wine['drinking_window_end'],
            'description' => $wine['description'],
            'lifecycle_status' => ProductLifecycleStatus::Draft,
            'data_source' => DataSource::LivEx,
            'lwin_code' => $wine['lwin'],
            'thumbnail_url' => $wine['image_url'],
            'locked_fields' => LivExService::LOCKED_FIELDS,
        ]);

        Notification::make()
            ->title('Wine imported successfully')
            ->body($wine['name'].' '.$wine['vintage'].' has been created as a draft product.')
            ->success()
            ->send();

        // Redirect to the edit page
        $this->redirect(WineVariantResource::getUrl('edit', ['record' => $wineVariant]));
    }

    /**
     * Get locked fields for display.
     *
     * @return list<string>
     */
    public function getLockedFields(): array
    {
        return LivExService::LOCKED_FIELDS;
    }
}
