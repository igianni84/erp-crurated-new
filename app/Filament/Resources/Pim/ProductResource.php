<?php

namespace App\Filament\Resources\Pim;

use App\Enums\DataSource;
use App\Enums\ProductLifecycleStatus;
use App\Filament\Resources\Pim\ProductResource\Pages;
use App\Models\Pim\WineVariant;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = WineVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'PIM';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $slug = 'pim/products';

    public static function form(Form $form): Form
    {
        // Products should be edited via Wine Variant or Liquid Product resources
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Thumbnail')
                    ->circular()
                    ->defaultImageUrl(function (WineVariant $record): string {
                        $wineMaster = $record->wineMaster;
                        $name = $wineMaster !== null ? $wineMaster->name : 'Wine';

                        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
                    })
                    ->size(40),
                Tables\Columns\TextColumn::make('name_vintage')
                    ->label('Name + Vintage')
                    ->getStateUsing(function (WineVariant $record): string {
                        $wineMaster = $record->wineMaster;
                        $name = $wineMaster !== null ? $wineMaster->name : 'Unknown';

                        return $name.' '.$record->vintage_year;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('wineMaster', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->join('wine_masters', 'wine_variants.wine_master_id', '=', 'wine_masters.id')
                        ->orderBy('wine_masters.name', $direction)
                        ->select('wine_variants.*')),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->getStateUsing(fn (WineVariant $record): string => $record->liquidProduct !== null ? 'liquid' : 'bottle')
                    ->formatStateUsing(fn (string $state): string => $state === 'liquid' ? 'Liquid' : 'Bottle')
                    ->color(fn (string $state): string => $state === 'liquid' ? 'info' : 'success')
                    ->icon(fn (string $state): string => $state === 'liquid' ? 'heroicon-o-beaker' : 'heroicon-o-cube'),
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
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('
                            (CASE WHEN alcohol_percentage IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_start IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN drinking_window_end IS NOT NULL THEN 1 ELSE 0 END +
                             CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 1 ELSE 0 END +
                             CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 1 ELSE 0 END)
                            '.$direction
                        );
                    }),
                Tables\Columns\TextColumn::make('data_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (DataSource $state): string => $state->label())
                    ->color(fn (DataSource $state): string => $state->color())
                    ->icon(fn (DataSource $state): string => $state->icon())
                    ->sortable(),
                Tables\Columns\TextColumn::make('internal_code')
                    ->label('Internal Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lwin_code')
                    ->label('LWIN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Status')
                    ->options(
                        collect(ProductLifecycleStatus::cases())
                            ->mapWithKeys(fn (ProductLifecycleStatus $status): array => [
                                $status->value => $status->label(),
                            ])
                            ->toArray()
                    ),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'bottle' => 'Bottle',
                        'liquid' => 'Liquid',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var 'bottle'|'liquid'|null $category */
                        $category = $data['value'] ?? null;
                        if ($category === 'bottle') {
                            return $query->whereDoesntHave('liquidProduct');
                        }
                        if ($category === 'liquid') {
                            return $query->whereHas('liquidProduct');
                        }

                        return $query;
                    }),
                Filter::make('completeness_range')
                    ->form([
                        \Filament\Forms\Components\Select::make('completeness')
                            ->label('Completeness')
                            ->options([
                                'low' => 'Low (< 50%)',
                                'medium' => 'Medium (50% - 80%)',
                                'high' => 'High (> 80%)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var 'low'|'medium'|'high'|null $completeness */
                        $completeness = $data['completeness'] ?? null;
                        if ($completeness === null) {
                            return $query;
                        }

                        // Filter in PHP by loading records and filtering
                        // This is a workaround since completeness is a computed value
                        $subQuery = match ($completeness) {
                            'low' => $query->whereRaw('
                                (CASE WHEN alcohol_percentage IS NOT NULL THEN 15 ELSE 0 END +
                                 CASE WHEN drinking_window_start IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN drinking_window_end IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 10 ELSE 0 END +
                                 CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 5 ELSE 0 END) < 50
                            '),
                            'medium' => $query->whereRaw('
                                (CASE WHEN alcohol_percentage IS NOT NULL THEN 15 ELSE 0 END +
                                 CASE WHEN drinking_window_start IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN drinking_window_end IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 10 ELSE 0 END +
                                 CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 5 ELSE 0 END) BETWEEN 50 AND 80
                            '),
                            'high' => $query->whereRaw('
                                (CASE WHEN alcohol_percentage IS NOT NULL THEN 15 ELSE 0 END +
                                 CASE WHEN drinking_window_start IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN drinking_window_end IS NOT NULL THEN 10 ELSE 0 END +
                                 CASE WHEN critic_scores IS NOT NULL AND critic_scores != "[]" AND critic_scores != "null" THEN 10 ELSE 0 END +
                                 CASE WHEN production_notes IS NOT NULL AND production_notes != "[]" AND production_notes != "null" THEN 5 ELSE 0 END) > 80
                            '),
                        };

                        return $subQuery;
                    }),
                Tables\Filters\SelectFilter::make('data_source')
                    ->label('Source')
                    ->options(
                        collect(DataSource::cases())
                            ->mapWithKeys(fn (DataSource $source): array => [
                                $source->value => $source->label(),
                            ])
                            ->toArray()
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (WineVariant $record): string => WineVariantResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()
                    ->url(fn (WineVariant $record): string => WineVariantResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('submit_for_review')
                        ->label('Submit for Review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Submit for Review')
                        ->modalDescription('Are you sure you want to submit the selected draft products for review?')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $submitted = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                /** @var WineVariant $record */
                                if ($record->canTransitionTo(ProductLifecycleStatus::InReview)) {
                                    $record->submitForReview();
                                    $submitted++;
                                } else {
                                    $skipped++;
                                }
                            }

                            if ($submitted > 0) {
                                Notification::make()
                                    ->title($submitted.' product(s) submitted for review')
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                Notification::make()
                                    ->title($skipped.' product(s) skipped (not in draft status)')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'choose-category' => Pages\ChooseProductCategory::route('/create'),
            'create-bottle' => Pages\CreateBottleProduct::route('/create/bottle'),
            'import-livex' => Pages\ImportLivex::route('/create/bottle/livex'),
        ];
    }
}
