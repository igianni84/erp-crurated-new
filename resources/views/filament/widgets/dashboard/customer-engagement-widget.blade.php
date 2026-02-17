<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-user-group class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                <span>Customer Engagement</span>
            </div>
        </x-slot>

        @php
            $customersByStatus = $this->getCustomersByStatus();
            $membershipsByTier = $this->getMembershipsByTier();
            $totalCustomers = $this->getTotalCustomers();
            $newCustomers = $this->getNewCustomersCount();

            $filamentColors = [
                'success' => ['bg' => 'bg-success-500', 'text' => 'text-success-600 dark:text-success-400', 'badge' => 'bg-success-50 dark:bg-success-400/10'],
                'warning' => ['bg' => 'bg-warning-500', 'text' => 'text-warning-600 dark:text-warning-400', 'badge' => 'bg-warning-50 dark:bg-warning-400/10'],
                'danger' => ['bg' => 'bg-danger-500', 'text' => 'text-danger-600 dark:text-danger-400', 'badge' => 'bg-danger-50 dark:bg-danger-400/10'],
                'gray' => ['bg' => 'bg-gray-400', 'text' => 'text-gray-600 dark:text-gray-400', 'badge' => 'bg-gray-50 dark:bg-white/5'],
                'primary' => ['bg' => 'bg-primary-500', 'text' => 'text-primary-600 dark:text-primary-400', 'badge' => 'bg-primary-50 dark:bg-primary-400/10'],
                'info' => ['bg' => 'bg-info-500', 'text' => 'text-info-600 dark:text-info-400', 'badge' => 'bg-info-50 dark:bg-info-400/10'],
            ];
        @endphp

        <div class="space-y-4">
            {{-- Summary Metrics --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Customers</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($totalCustomers) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">New This Period</p>
                    <p class="text-lg font-bold text-success-600 dark:text-success-400">{{ number_format($newCustomers) }}</p>
                </div>
            </div>

            {{-- Customer Status Breakdown --}}
            <div>
                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Status Breakdown</p>
                @if($totalCustomers > 0)
                    <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        @foreach($customersByStatus as $status)
                            @if($status['count'] > 0)
                                <div
                                    class="{{ $filamentColors[$status['color']]['bg'] ?? 'bg-gray-400' }}"
                                    style="width: {{ ($status['count'] / $totalCustomers) * 100 }}%"
                                    title="{{ $status['label'] }}: {{ number_format($status['count']) }}"
                                ></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        @foreach($customersByStatus as $status)
                            @if($status['count'] > 0)
                                <div class="flex items-center gap-1">
                                    <div class="h-2 w-2 rounded-full {{ $filamentColors[$status['color']]['bg'] ?? 'bg-gray-400' }}"></div>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ $status['label'] }} ({{ number_format($status['count']) }})</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Membership Tiers --}}
            <div>
                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Active Memberships by Tier</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($membershipsByTier as $tier)
                        @php
                            $colors = $filamentColors[$tier['color']] ?? $filamentColors['gray'];
                        @endphp
                        <div class="flex items-center gap-1.5 rounded-full px-3 py-1 {{ $colors['badge'] }}">
                            <span class="text-xs font-medium {{ $colors['text'] }}">{{ $tier['label'] }}</span>
                            <span class="text-xs font-bold {{ $colors['text'] }}">{{ number_format($tier['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
