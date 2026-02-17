<?php

namespace App\Filament\Resources\Procurement\SupplierProducerResource\Pages;

use App\Enums\Customer\PartyRoleType;
use App\Filament\Resources\Procurement\SupplierProducerResource;
use App\Models\Customer\Party;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupplierProducers extends ListRecords
{
    protected static string $resource = SupplierProducerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - this is a read-focused view
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => $this->getAllCount()),

            'suppliers' => Tab::make('Suppliers')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                    'roles',
                    fn (Builder $q): Builder => $q->where('role', PartyRoleType::Supplier->value),
                ))
                ->badge(fn (): int => $this->getSupplierCount()),

            'producers' => Tab::make('Producers')
                ->icon('heroicon-o-beaker')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                    'roles',
                    fn (Builder $q): Builder => $q->where('role', PartyRoleType::Producer->value),
                ))
                ->badge(fn (): int => $this->getProducerCount()),

            'with_config' => Tab::make('With Config')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('supplierConfig'))
                ->badge(fn (): int => $this->getWithConfigCount())
                ->badgeColor('success'),

            'without_config' => Tab::make('Without Config')
                ->icon('heroicon-o-exclamation-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDoesntHave('supplierConfig'))
                ->badge(fn (): int => $this->getWithoutConfigCount())
                ->badgeColor('warning'),
        ];
    }

    /**
     * Get the count of all suppliers/producers.
     */
    protected function getAllCount(): int
    {
        return Party::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('role', [
                PartyRoleType::Supplier->value,
                PartyRoleType::Producer->value,
            ]))
            ->count();
    }

    /**
     * Get the count of suppliers.
     */
    protected function getSupplierCount(): int
    {
        return Party::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('role', PartyRoleType::Supplier->value))
            ->count();
    }

    /**
     * Get the count of producers.
     */
    protected function getProducerCount(): int
    {
        return Party::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('role', PartyRoleType::Producer->value))
            ->count();
    }

    /**
     * Get the count of parties with supplier config.
     */
    protected function getWithConfigCount(): int
    {
        return Party::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('role', [
                PartyRoleType::Supplier->value,
                PartyRoleType::Producer->value,
            ]))
            ->whereHas('supplierConfig')
            ->count();
    }

    /**
     * Get the count of parties without supplier config.
     */
    protected function getWithoutConfigCount(): int
    {
        return Party::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('role', [
                PartyRoleType::Supplier->value,
                PartyRoleType::Producer->value,
            ]))
            ->whereDoesntHave('supplierConfig')
            ->count();
    }
}
