<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource;
use App\Models\Commercial\DiscountRule;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewDiscountRule extends ViewRecord
{
    protected static string $resource = DiscountRuleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var DiscountRule $record */
        $record = $this->getRecord();

        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => $record->canBeEdited()),
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Activate Discount Rule')
                ->modalDescription('Are you sure you want to activate this discount rule?')
                ->visible(fn (): bool => $record->isInactive())
                ->action(function () use ($record): void {
                    $record->status = DiscountRuleStatus::Active;
                    $record->save();

                    Notification::make()
                        ->title('Discount rule activated')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Deactivate Discount Rule')
                ->modalDescription(fn (): string => $record->hasActiveOffersUsing()
                    ? 'Warning: This rule is used by active Offers. Deactivating it may affect pricing.'
                    : 'Are you sure you want to deactivate this discount rule?')
                ->visible(fn (): bool => $record->isActive() && $record->canBeDeactivated())
                ->action(function () use ($record): void {
                    $record->status = DiscountRuleStatus::Inactive;
                    $record->save();

                    Notification::make()
                        ->title('Discount rule deactivated')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $record->canBeDeleted())
                ->before(function (Actions\DeleteAction $action) use ($record): void {
                    if ($record->isReferencedByAnyOffer()) {
                        Notification::make()
                            ->title('Cannot delete')
                            ->body('This rule is referenced by Offers and cannot be deleted.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Rule Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('rule_type')
                            ->label('Rule Type')
                            ->badge()
                            ->formatStateUsing(fn (DiscountRuleType $state): string => $state->label())
                            ->color(fn (DiscountRuleType $state): string => $state->color())
                            ->icon(fn (DiscountRuleType $state): string => $state->icon()),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (DiscountRuleStatus $state): string => $state->label())
                            ->color(fn (DiscountRuleStatus $state): string => $state->color())
                            ->icon(fn (DiscountRuleStatus $state): string => $state->icon()),
                        Infolists\Components\TextEntry::make('summary')
                            ->label('Summary')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->getSummary())
                            ->icon('heroicon-o-document-text'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Logic Configuration')
                    ->description('The discount calculation logic for this rule.')
                    ->schema([
                        Infolists\Components\TextEntry::make('rule_type_description')
                            ->label('How it works')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->rule_type->description())
                            ->columnSpanFull(),

                        // Percentage/Fixed Amount value
                        Infolists\Components\TextEntry::make('discount_value')
                            ->label(fn (DiscountRule $record): string => $record->isPercentage() ? 'Discount Percentage' : 'Discount Amount')
                            ->getStateUsing(function (DiscountRule $record): string {
                                $value = $record->getValue();
                                if ($value === null) {
                                    return 'Not configured';
                                }

                                return $record->isPercentage()
                                    ? number_format($value, 1).'%'
                                    : '€'.number_format($value, 2);
                            })
                            ->visible(fn (DiscountRule $record): bool => $record->isPercentage() || $record->isFixedAmount())
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->color('success'),

                        // Tiered logic display
                        Infolists\Components\TextEntry::make('tiers_display')
                            ->label('Price Tiers')
                            ->getStateUsing(fn (DiscountRule $record): HtmlString => $this->formatTiersDisplay($record))
                            ->html()
                            ->visible(fn (DiscountRule $record): bool => $record->isTiered())
                            ->columnSpanFull(),

                        // Volume-based thresholds display
                        Infolists\Components\TextEntry::make('thresholds_display')
                            ->label('Quantity Thresholds')
                            ->getStateUsing(fn (DiscountRule $record): HtmlString => $this->formatThresholdsDisplay($record))
                            ->html()
                            ->visible(fn (DiscountRule $record): bool => $record->isVolumeBased())
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Usage')
                    ->description('Offers using this discount rule.')
                    ->schema([
                        Infolists\Components\TextEntry::make('offers_using_count')
                            ->label('Total Offers Using')
                            ->getStateUsing(fn (DiscountRule $record): string => (string) $record->getOffersUsingCount())
                            ->badge()
                            ->color(fn (DiscountRule $record): string => $record->getOffersUsingCount() > 0 ? 'info' : 'gray'),
                        Infolists\Components\TextEntry::make('active_offers_using_count')
                            ->label('Active Offers Using')
                            ->getStateUsing(fn (DiscountRule $record): string => (string) $record->getActiveOffersUsingCount())
                            ->badge()
                            ->color(fn (DiscountRule $record): string => $record->getActiveOffersUsingCount() > 0 ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('editability')
                            ->label('Edit Status')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->canBeEdited()
                                ? 'This rule can be edited'
                                : 'This rule cannot be edited (active Offers are using it)')
                            ->icon(fn (DiscountRule $record): string => $record->canBeEdited()
                                ? 'heroicon-o-pencil-square'
                                : 'heroicon-o-lock-closed')
                            ->color(fn (DiscountRule $record): string => $record->canBeEdited() ? 'success' : 'warning')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('deletability')
                            ->label('Delete Status')
                            ->getStateUsing(fn (DiscountRule $record): string => $record->canBeDeleted()
                                ? 'This rule can be deleted'
                                : 'This rule cannot be deleted (referenced by Offers)')
                            ->icon(fn (DiscountRule $record): string => $record->canBeDeleted()
                                ? 'heroicon-o-trash'
                                : 'heroicon-o-shield-exclamation')
                            ->color(fn (DiscountRule $record): string => $record->canBeDeleted() ? 'danger' : 'warning')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    /**
     * Format tiered logic for display.
     */
    private function formatTiersDisplay(DiscountRule $record): HtmlString
    {
        $tiers = $record->getTiers();

        if (empty($tiers)) {
            return new HtmlString('<span class="text-gray-500">No tiers configured</span>');
        }

        $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tier</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price Range</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($tiers as $i => $tier) {
            $minVal = $tier['min'] ?? null;
            $maxVal = $tier['max'] ?? null;
            $min = ($minVal !== null) ? '€'.number_format((float) $minVal, 2) : '€0.00';
            $max = ($maxVal !== null) ? '€'.number_format((float) $maxVal, 2) : '∞';
            $value = number_format((float) ($tier['value'] ?? 0), 1).'%';

            $html .= '<tr>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Tier '.($i + 1).'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">'.$min.' - '.$max.'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm"><span class="inline-flex items-center rounded-full bg-success-100 dark:bg-success-900/20 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:text-success-200">'.$value.' off</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Format volume-based thresholds for display.
     */
    private function formatThresholdsDisplay(DiscountRule $record): HtmlString
    {
        $thresholds = $record->getThresholds();

        if (empty($thresholds)) {
            return new HtmlString('<span class="text-gray-500">No thresholds configured</span>');
        }

        $html = '<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">';
        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
        $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
        $html .= '<tr>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Threshold</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Minimum Quantity</th>';
        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Discount Amount</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

        foreach ($thresholds as $i => $threshold) {
            $minQty = (int) $threshold['min_qty'];
            $value = '€'.number_format((float) $threshold['value'], 2);

            $html .= '<tr>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">Threshold '.($i + 1).'</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">≥ '.$minQty.' units</td>';
            $html .= '<td class="px-4 py-2 whitespace-nowrap text-sm"><span class="inline-flex items-center rounded-full bg-warning-100 dark:bg-warning-900/20 px-2.5 py-0.5 text-xs font-medium text-warning-800 dark:text-warning-200">'.$value.' off</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return new HtmlString($html);
    }
}
