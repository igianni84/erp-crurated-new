<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\Inventory\BottleState;
use App\Enums\Inventory\CaseIntegrityStatus;
use App\Models\Inventory\InventoryCase;
use App\Models\Inventory\Location;
use App\Models\Inventory\SerializedBottle;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class InventoryPositionWidget extends Widget
{
    protected string $view = 'filament.widgets.dashboard.inventory-position-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 2;

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<string, array{count: int, label: string, color: string}>
     */
    public function getBottlesByState(): array
    {
        return Cache::remember('inventory_bottles_by_state', 60, function () {
            $results = [];

            foreach (BottleState::cases() as $state) {
                $count = SerializedBottle::where('state', $state->value)->count();
                if ($count > 0) {
                    $results[$state->value] = [
                        'count' => $count,
                        'label' => $state->label(),
                        'color' => $state->color(),
                    ];
                }
            }

            return $results;
        });
    }

    public function getTotalBottles(): int
    {
        return Cache::remember('inventory_total_bottles', 60, function () {
            return SerializedBottle::count();
        });
    }

    /**
     * @return array{intact: int, broken: int}
     */
    public function getCaseIntegrity(): array
    {
        return Cache::remember('inventory_case_integrity', 60, function () {
            return [
                'intact' => InventoryCase::where('integrity_status', CaseIntegrityStatus::Intact->value)->count(),
                'broken' => InventoryCase::where('integrity_status', CaseIntegrityStatus::Broken->value)->count(),
            ];
        });
    }

    public function getLocationCount(): int
    {
        return Cache::remember('inventory_location_count', 60, function () {
            return Location::count();
        });
    }
}
