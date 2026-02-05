<?php

namespace App\Filament\Resources\Procurement\PurchaseOrderResource\Pages;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Purchase Order')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PurchaseOrderStatus::Draft->value,
                    PurchaseOrderStatus::Sent->value,
                    PurchaseOrderStatus::Confirmed->value,
                ]))
                ->badge($this->getActiveCount())
                ->badgeColor('primary'),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Draft->value))
                ->badge($this->getDraftCount())
                ->badgeColor('gray'),

            'sent' => Tab::make('Sent')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Sent->value))
                ->badge($this->getSentCount())
                ->badgeColor('warning'),

            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Confirmed->value))
                ->badge($this->getConfirmedCount())
                ->badgeColor('success'),

            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('expected_delivery_end')
                    ->where('expected_delivery_end', '<', now())
                    ->where('status', '!=', PurchaseOrderStatus::Closed->value))
                ->badge($this->getOverdueCount())
                ->badgeColor('danger'),

            'variance' => Tab::make('With Variance')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('inbounds')
                    ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) != purchase_orders.quantity'))
                ->badge($this->getVarianceCount())
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),

            'significant_variance' => Tab::make('Variance > 10%')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('inbounds')
                    ->where('quantity', '>', 0)
                    ->whereRaw('ABS((SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) - purchase_orders.quantity) / purchase_orders.quantity * 100 > 10'))
                ->badge($this->getSignificantVarianceCount())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-circle'),

            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Closed->value))
                ->badge($this->getClosedCount())
                ->badgeColor('gray'),

            'all' => Tab::make('All')
                ->badge($this->getAllCount()),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }

    private function getActiveCount(): int
    {
        return PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Draft->value,
                PurchaseOrderStatus::Sent->value,
                PurchaseOrderStatus::Confirmed->value,
            ])
            ->count();
    }

    private function getDraftCount(): int
    {
        return PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Draft->value)
            ->count();
    }

    private function getSentCount(): int
    {
        return PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Sent->value)
            ->count();
    }

    private function getConfirmedCount(): int
    {
        return PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Confirmed->value)
            ->count();
    }

    private function getOverdueCount(): int
    {
        return PurchaseOrder::query()
            ->whereNotNull('expected_delivery_end')
            ->where('expected_delivery_end', '<', now())
            ->where('status', '!=', PurchaseOrderStatus::Closed->value)
            ->count();
    }

    private function getClosedCount(): int
    {
        return PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Closed->value)
            ->count();
    }

    private function getAllCount(): int
    {
        return PurchaseOrder::count();
    }

    private function getVarianceCount(): int
    {
        return PurchaseOrder::query()
            ->whereHas('inbounds')
            ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) != purchase_orders.quantity')
            ->count();
    }

    private function getSignificantVarianceCount(): int
    {
        return PurchaseOrder::query()
            ->whereHas('inbounds')
            ->where('quantity', '>', 0)
            ->whereRaw('ABS((SELECT COALESCE(SUM(quantity), 0) FROM inbounds WHERE inbounds.purchase_order_id = purchase_orders.id AND inbounds.deleted_at IS NULL) - purchase_orders.quantity) / purchase_orders.quantity * 100 > 10')
            ->count();
    }
}
