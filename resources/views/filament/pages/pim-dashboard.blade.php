<x-filament-panels::page>
    @php
        $statusCounts = $this->getStatusCounts();
        $statusMeta = $this->getStatusMeta();
        $completeness = $this->getCompletenessDistribution();
        $blockingIssues = $this->getBlockingIssuesSummary();
        $blockedProducts = $this->getBlockedProducts();
        $totals = $this->getTotals();
    @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        {{-- Total Products --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/10 p-3">
                    <x-heroicon-o-cube class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Products</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $totals['total'] }}</p>
                </div>
            </div>
        </div>

        {{-- Publishable Products --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-success-50 dark:bg-success-400/10 p-3">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Publishable</p>
                    <p class="text-2xl font-semibold text-success-600 dark:text-success-400">{{ $totals['publishable'] }}</p>
                </div>
            </div>
        </div>

        {{-- Blocked Products --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 rounded-lg bg-danger-50 dark:bg-danger-400/10 p-3">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Blocked</p>
                    <p class="text-2xl font-semibold text-danger-600 dark:text-danger-400">{{ $totals['blocked'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Products by Status --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-chart-pie class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Products by Status
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="space-y-4">
                    @foreach($statusCounts as $status => $count)
                        @php
                            $meta = $statusMeta[$status];
                            $percentage = $totals['total'] > 0 ? round(($count / $totals['total']) * 100) : 0;
                            $colorClass = match($meta['color']) {
                                'gray' => 'bg-gray-500',
                                'warning' => 'bg-warning-500',
                                'info' => 'bg-info-500',
                                'success' => 'bg-success-500',
                                'danger' => 'bg-danger-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center">
                                    <x-dynamic-component :component="$meta['icon']" class="h-4 w-4 mr-2" />
                                    {{ $meta['label'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="{{ $colorClass }} h-2 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Completeness Distribution --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    <x-heroicon-o-chart-bar class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                    Completeness Distribution
                </h3>
            </div>
            <div class="fi-section-content p-6">
                <div class="grid grid-cols-3 gap-4 text-center">
                    {{-- Low (<50%) --}}
                    <div class="rounded-lg bg-danger-50 dark:bg-danger-400/10 p-4">
                        <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $completeness['low'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Low (&lt;50%)</div>
                        <div class="flex justify-center mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400">
                                <x-heroicon-o-exclamation-circle class="h-3 w-3 mr-1" />
                                Needs work
                            </span>
                        </div>
                    </div>

                    {{-- Medium (50-80%) --}}
                    <div class="rounded-lg bg-warning-50 dark:bg-warning-400/10 p-4">
                        <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $completeness['medium'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">Medium (50-80%)</div>
                        <div class="flex justify-center mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400">
                                <x-heroicon-o-arrow-trending-up class="h-3 w-3 mr-1" />
                                In progress
                            </span>
                        </div>
                    </div>

                    {{-- High (>80%) --}}
                    <div class="rounded-lg bg-success-50 dark:bg-success-400/10 p-4">
                        <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $completeness['high'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">High (&gt;80%)</div>
                        <div class="flex justify-center mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400">
                                <x-heroicon-o-check class="h-3 w-3 mr-1" />
                                Good
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Blocking Issues Summary --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-exclamation-triangle class="inline-block h-5 w-5 mr-2 -mt-0.5 text-danger-500" />
                Blocking Issues by Type
            </h3>
        </div>
        <div class="fi-section-content p-6">
            @if(count($blockingIssues) > 0)
                <div class="space-y-3">
                    @foreach($blockingIssues as $issue => $count)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <div class="flex items-center">
                                <x-heroicon-o-x-circle class="h-5 w-5 text-danger-500 mr-3 flex-shrink-0" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $issue }}</span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400">
                                {{ $count }} product{{ $count !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-6">
                    <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No blocking issues found. All products are ready for publication!</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Blocked Products List --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4 flex justify-between items-center">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                <x-heroicon-o-queue-list class="inline-block h-5 w-5 mr-2 -mt-0.5" />
                Blocked Products ({{ $blockedProducts->count() }})
            </h3>
            @if($blockedProducts->count() > 0)
                <button
                    wire:click="exportIssues"
                    type="button"
                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4 mr-1.5" />
                    Export CSV
                </button>
            @endif
        </div>
        <div class="fi-section-content">
            @if($blockedProducts->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Product
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Completeness
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Blocking Issues
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($blockedProducts as $product)
                                @php
                                    $wineMaster = $product->wineMaster;
                                    $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown';
                                    $issues = $product->getBlockingIssues();
                                    $completenessPercentage = $product->getCompletenessPercentage();
                                    $completenessColor = $product->getCompletenessColor();
                                    $statusMeta = $statusMeta[$product->lifecycle_status->value] ?? ['label' => 'Unknown', 'color' => 'gray'];
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if($product->thumbnail_url)
                                                    <img class="h-10 w-10 rounded-full object-cover" src="{{ $product->thumbnail_url }}" alt="{{ $wineName }}">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                                        <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">{{ substr($wineName, 0, 2) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $wineName }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $product->vintage_year }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badgeColor = match($statusMeta['color']) {
                                                'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400',
                                                'info' => 'bg-info-100 text-info-800 dark:bg-info-400/20 dark:text-info-400',
                                                'success' => 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400',
                                                'danger' => 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeColor }}">
                                            {{ $statusMeta['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $completenessBadgeColor = match($completenessColor) {
                                                'danger' => 'bg-danger-100 text-danger-800 dark:bg-danger-400/20 dark:text-danger-400',
                                                'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-400/20 dark:text-warning-400',
                                                'success' => 'bg-success-100 text-success-800 dark:bg-success-400/20 dark:text-success-400',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $completenessBadgeColor }}">
                                            {{ $completenessPercentage }}%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            @foreach($issues as $issue)
                                                <a
                                                    href="{{ $this->getProductEditUrl($product, $issue['tab']) }}"
                                                    class="flex items-center text-sm text-danger-600 dark:text-danger-400 hover:text-danger-800 dark:hover:text-danger-300 hover:underline"
                                                >
                                                    <x-heroicon-o-exclamation-circle class="h-4 w-4 mr-1.5 flex-shrink-0" />
                                                    <span>{{ $issue['message'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a
                                                href="{{ $this->getProductViewUrl($product) }}"
                                                class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                                                title="View"
                                            >
                                                <x-heroicon-o-eye class="h-5 w-5" />
                                            </a>
                                            <a
                                                href="{{ $this->getProductEditUrl($product) }}"
                                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                                                title="Edit"
                                            >
                                                <x-heroicon-o-pencil-square class="h-5 w-5" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Blocked Products</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All products are ready for publication workflow.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
