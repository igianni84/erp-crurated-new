<?php

namespace App\Filament\Resources\DiscountRuleResource\Pages;

use App\Enums\Commercial\DiscountRuleType;
use App\Filament\Resources\DiscountRuleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDiscountRule extends EditRecord
{
    protected static string $resource = DiscountRuleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\Commercial\DiscountRule $record */
        $record = $this->getRecord();

        return [
            Actions\ViewAction::make(),
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

    /**
     * Check if the user can edit this record.
     * Prevents editing if active Offers are using this rule.
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var \App\Models\Commercial\DiscountRule $record */
        $record = $this->getRecord();

        if (! $record->canBeEdited()) {
            Notification::make()
                ->title('Cannot edit')
                ->body('This rule cannot be edited because active Offers are using it.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
        }
    }

    /**
     * Mutate form data before saving the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure logic_definition is properly structured based on rule_type
        $ruleType = isset($data['rule_type']) ? DiscountRuleType::tryFrom($data['rule_type']) : null;

        if ($ruleType !== null) {
            $logicDefinition = $data['logic_definition'] ?? [];

            // Clean up logic_definition based on rule type
            $data['logic_definition'] = match ($ruleType) {
                DiscountRuleType::Percentage,
                DiscountRuleType::FixedAmount => [
                    'value' => isset($logicDefinition['value']) ? (float) $logicDefinition['value'] : null,
                ],
                DiscountRuleType::Tiered => [
                    'tiers' => $this->cleanTiers($logicDefinition['tiers'] ?? []),
                ],
                DiscountRuleType::VolumeBased => [
                    'thresholds' => $this->cleanThresholds($logicDefinition['thresholds'] ?? []),
                ],
            };
        }

        return $data;
    }

    /**
     * Clean and validate tiered discount tiers.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array<int, array{min: float, max: float|null, value: float}>
     */
    private function cleanTiers(array $tiers): array
    {
        $cleanedTiers = [];
        foreach ($tiers as $tier) {
            $value = $tier['value'] ?? null;
            if ($value !== '' && $value !== null) {
                $min = $tier['min'] ?? null;
                $max = $tier['max'] ?? null;
                $cleanedTiers[] = [
                    'min' => $min !== '' && $min !== null ? (float) $min : 0,
                    'max' => $max !== '' && $max !== null ? (float) $max : null,
                    'value' => (float) $value,
                ];
            }
        }

        return $cleanedTiers;
    }

    /**
     * Clean and validate volume-based thresholds.
     *
     * @param  array<int, array<string, mixed>>  $thresholds
     * @return array<int, array{min_qty: int, value: float}>
     */
    private function cleanThresholds(array $thresholds): array
    {
        $cleanedThresholds = [];
        foreach ($thresholds as $threshold) {
            $minQty = $threshold['min_qty'] ?? null;
            $value = $threshold['value'] ?? null;
            if ($minQty !== '' && $minQty !== null && $value !== '' && $value !== null) {
                $cleanedThresholds[] = [
                    'min_qty' => (int) $minQty,
                    'value' => (float) $value,
                ];
            }
        }

        // Sort thresholds by min_qty ascending
        usort($cleanedThresholds, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        return $cleanedThresholds;
    }

    /**
     * Get the redirect URL after saving the record.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
