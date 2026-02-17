<?php

namespace App\Filament\Pages;

use App\Models\AI\AiAuditLog;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AiAuditViewer extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'AI Audit Log';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'AI Audit Log';

    protected string $view = 'filament.pages.ai-audit-viewer';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && ($user->isSuperAdmin() || $user->isAdmin());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AiAuditLog::query()->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('message_text')
                    ->label('Message')
                    ->limit(60)
                    ->tooltip(fn (AiAuditLog $record): string => (string) $record->message_text)
                    ->searchable(),
                TextColumn::make('tokens_input')
                    ->label('Tokens In')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tokens_output')
                    ->label('Tokens Out')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estimated_cost_eur')
                    ->label('Cost (EUR)')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? number_format($state / 1000, 1).'s' : '-')
                    ->sortable(),
                TextColumn::make('conversation_id')
                    ->label('Conversation')
                    ->limit(8)
                    ->tooltip(fn (AiAuditLog $record): ?string => $record->conversation_id)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::whereNotNull('role')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->where('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, string $date): Builder => $q->where('created_at', '<=', $date.' 23:59:59'),
                            );
                    }),
            ])
            ->paginated([10, 25, 50])
            ->toolbarActions([]);
    }
}
