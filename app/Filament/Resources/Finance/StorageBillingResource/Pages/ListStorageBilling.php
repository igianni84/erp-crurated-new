<?php

namespace App\Filament\Resources\Finance\StorageBillingResource\Pages;

use App\Enums\Finance\StorageBillingStatus;
use App\Filament\Resources\Finance\StorageBillingResource;
use App\Jobs\Finance\GenerateStorageBillingJob;
use App\Models\Finance\StorageBillingPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListStorageBilling extends ListRecords
{
    protected static string $resource = StorageBillingResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getGenerateBillingAction(),
        ];
    }

    /**
     * Generate Billing action with preview and confirmation.
     */
    protected function getGenerateBillingAction(): Action
    {
        return Action::make('generate_billing')
            ->label('Generate Billing')
            ->icon('heroicon-o-calculator')
            ->color('primary')
            ->form([
                Section::make('Billing Period')
                    ->description('Select the billing period to generate storage billing for.')
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Period Start')
                            ->required()
                            ->default(now()->subMonth()->startOfMonth())
                            ->maxDate(now())
                            ->native(false)
                            ->live(),

                        DatePicker::make('period_end')
                            ->label('Period End')
                            ->required()
                            ->default(now()->subMonth()->endOfMonth())
                            ->maxDate(now())
                            ->native(false)
                            ->live()
                            ->afterOrEqual('period_start'),

                        Toggle::make('auto_generate_invoices')
                            ->label('Auto-generate INV3 invoices')
                            ->helperText('When enabled, INV3 invoices will be created automatically for each billing period.')
                            ->default(true)
                            ->live(),

                        Toggle::make('auto_issue')
                            ->label('Auto-issue invoices')
                            ->helperText('When enabled, generated invoices will be automatically issued (not just drafts).')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('auto_generate_invoices') === true),
                    ])
                    ->columns(2),

                Section::make('Preview')
                    ->description('Preview of affected customers and amounts')
                    ->schema([
                        Placeholder::make('preview')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                $periodStart = $get('period_start');
                                $periodEnd = $get('period_end');

                                if ($periodStart === null || $periodEnd === null) {
                                    return new HtmlString('<p class="text-gray-500">Select a period to see preview.</p>');
                                }

                                $startDate = Carbon::parse($periodStart);
                                $endDate = Carbon::parse($periodEnd);

                                if ($endDate->lt($startDate)) {
                                    return new HtmlString('<p class="text-danger-500">End date must be after start date.</p>');
                                }

                                // Get preview data using the job's preview methods
                                $job = new GenerateStorageBillingJob($startDate, $endDate, false, false);
                                $summary = $job->getPreviewSummary();
                                $previewData = $job->getPreviewData();

                                if ($summary['total_customers'] === 0) {
                                    return new HtmlString('<p class="text-gray-500">No customers with storage usage in this period.</p>');
                                }

                                $html = '<div class="space-y-4">';

                                // Summary stats
                                $html .= '<div class="grid grid-cols-3 gap-4">';
                                $html .= '<div class="bg-primary-50 dark:bg-primary-900/20 p-3 rounded-lg">';
                                $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">Customers</div>';
                                $html .= '<div class="text-xl font-bold text-primary-600 dark:text-primary-400">'.$summary['total_customers'].'</div>';
                                $html .= '</div>';

                                $html .= '<div class="bg-primary-50 dark:bg-primary-900/20 p-3 rounded-lg">';
                                $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">Total Bottle-Days</div>';
                                $html .= '<div class="text-xl font-bold text-primary-600 dark:text-primary-400">'.number_format($summary['total_bottle_days']).'</div>';
                                $html .= '</div>';

                                $html .= '<div class="bg-primary-50 dark:bg-primary-900/20 p-3 rounded-lg">';
                                $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">Total Amount</div>';
                                $html .= '<div class="text-xl font-bold text-primary-600 dark:text-primary-400">'.$summary['currency'].' '.number_format((float) $summary['total_amount'], 2).'</div>';
                                $html .= '</div>';
                                $html .= '</div>';

                                // Customer breakdown (first 10)
                                $html .= '<div class="mt-4">';
                                $html .= '<div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Customer Breakdown</div>';
                                $html .= '<div class="overflow-x-auto">';
                                $html .= '<table class="min-w-full text-sm">';
                                $html .= '<thead class="bg-gray-50 dark:bg-gray-800">';
                                $html .= '<tr><th class="px-2 py-1 text-left">Customer</th><th class="px-2 py-1 text-right">Bottles</th><th class="px-2 py-1 text-right">Bottle-Days</th><th class="px-2 py-1 text-right">Amount</th><th class="px-2 py-1 text-center">Status</th></tr>';
                                $html .= '</thead><tbody>';

                                $displayed = 0;
                                foreach ($previewData as $item) {
                                    if ($displayed >= 10) {
                                        break;
                                    }

                                    $statusBadge = $item['has_existing_period']
                                        ? '<span class="px-2 py-0.5 text-xs rounded bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">Exists</span>'
                                        : '<span class="px-2 py-0.5 text-xs rounded bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300">New</span>';

                                    $html .= '<tr class="border-b dark:border-gray-700">';
                                    $html .= '<td class="px-2 py-1">'.e($item['customer_name']).'</td>';
                                    $html .= '<td class="px-2 py-1 text-right">'.number_format($item['bottle_count']).'</td>';
                                    $html .= '<td class="px-2 py-1 text-right">'.number_format($item['bottle_days']).'</td>';
                                    $html .= '<td class="px-2 py-1 text-right">'.$item['currency'].' '.number_format((float) $item['calculated_amount'], 2).'</td>';
                                    $html .= '<td class="px-2 py-1 text-center">'.$statusBadge.'</td>';
                                    $html .= '</tr>';
                                    $displayed++;
                                }

                                $html .= '</tbody></table>';
                                $html .= '</div>';

                                if ($previewData->count() > 10) {
                                    $remaining = $previewData->count() - 10;
                                    $html .= '<p class="text-sm text-gray-500 mt-2">...and '.$remaining.' more customers</p>';
                                }

                                $html .= '</div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsible(),
            ])
            ->modalHeading('Generate Storage Billing')
            ->modalDescription('Generate storage billing periods and optionally create INV3 invoices for the selected period.')
            ->modalSubmitActionLabel('Generate Billing')
            ->modalWidth('3xl')
            ->action(function (array $data): void {
                $periodStart = Carbon::parse($data['period_start']);
                $periodEnd = Carbon::parse($data['period_end']);
                $autoGenerateInvoices = $data['auto_generate_invoices'] ?? true;
                $autoIssue = $data['auto_issue'] ?? true;

                // Dispatch the job
                GenerateStorageBillingJob::dispatch(
                    $periodStart,
                    $periodEnd,
                    $autoGenerateInvoices,
                    $autoIssue
                );

                Notification::make()
                    ->title('Storage Billing Generation Started')
                    ->body('The billing generation job has been queued. Check back shortly for the generated billing periods.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-archive-box')
                ->badge(fn (): int => StorageBillingPeriod::count()),

            'current_period' => Tab::make('Current Period')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('period_start', '>=', $currentPeriodStart)
                    ->where('period_end', '<=', $currentPeriodEnd))
                ->badge(fn (): int => StorageBillingPeriod::query()
                    ->where('period_start', '>=', $currentPeriodStart)
                    ->where('period_end', '<=', $currentPeriodEnd)
                    ->count())
                ->badgeColor('info'),

            'past_periods' => Tab::make('Past Periods')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('period_end', '<', $currentPeriodStart))
                ->badge(fn (): int => StorageBillingPeriod::query()
                    ->where('period_end', '<', $currentPeriodStart)
                    ->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-document')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StorageBillingStatus::Pending))
                ->badge(fn (): int => StorageBillingPeriod::where('status', StorageBillingStatus::Pending)->count())
                ->badgeColor('warning'),

            'blocked' => Tab::make('Blocked')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StorageBillingStatus::Blocked))
                ->badge(fn (): int => StorageBillingPeriod::where('status', StorageBillingStatus::Blocked)->count())
                ->badgeColor('danger'),
        ];
    }
}
