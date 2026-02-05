<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-users class="h-5 w-5 text-primary-500" />
                Preference Collection Progress
            </div>
        </x-slot>

        <x-slot name="description">
            Track customer bottling preference collection for this instruction
        </x-slot>

        @php
            $progress = $this->getProgressData();
            $progressColor = $this->getProgressColor();
            $vouchers = $this->getPaginatedVoucherList();
            $totalPages = $this->getTotalPages();
            $allVouchers = $this->getVoucherList();
        @endphp

        <div class="space-y-6">
            {{-- Progress Bar Section --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Collection Progress</span>
                    <span class="text-sm font-bold text-{{ $progressColor }}-600 dark:text-{{ $progressColor }}-400">
                        {{ $progress['percentage'] }}%
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                    <div
                        class="h-4 rounded-full transition-all duration-500 ease-out
                            @if($progressColor === 'success') bg-success-500
                            @elseif($progressColor === 'warning') bg-warning-500
                            @elseif($progressColor === 'info') bg-info-500
                            @else bg-gray-400
                            @endif"
                        style="width: {{ min($progress['percentage'], 100) }}%"
                    ></div>
                </div>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-3 gap-4 mt-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                            {{ $progress['collected'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Collected</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                            {{ $progress['pending'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                            {{ $progress['total'] }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Vouchers</div>
                    </div>
                </div>
            </div>

            {{-- Status Messages --}}
            @if($this->isDeadlinePassed())
                <div class="flex items-center gap-2 p-3 bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg">
                    <x-heroicon-o-exclamation-circle class="h-5 w-5 text-danger-500 flex-shrink-0" />
                    <span class="text-sm text-danger-700 dark:text-danger-300">
                        Deadline has passed. Default bottling rules have been or will be applied to pending preferences.
                    </span>
                </div>
            @elseif(!$this->canCollectPreferences())
                <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-gray-500 flex-shrink-0" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Preference collection is not active for this instruction.
                    </span>
                </div>
            @elseif($progress['pending'] > 0)
                <div class="flex items-center gap-2 p-3 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg">
                    <x-heroicon-o-clock class="h-5 w-5 text-warning-500 flex-shrink-0" />
                    <span class="text-sm text-warning-700 dark:text-warning-300">
                        {{ $progress['pending'] }} customer(s) have not yet submitted their bottling preferences.
                    </span>
                </div>
            @else
                <div class="flex items-center gap-2 p-3 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-lg">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-success-500 flex-shrink-0" />
                    <span class="text-sm text-success-700 dark:text-success-300">
                        All customer preferences have been collected!
                    </span>
                </div>
            @endif

            {{-- Customer Portal Link --}}
            <div class="flex items-center justify-between p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="h-5 w-5 text-primary-500" />
                    <span class="text-sm text-primary-700 dark:text-primary-300">
                        Customer Portal for Preference Collection
                    </span>
                </div>
                <a
                    href="{{ $this->getPortalUrl() }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    Open Portal
                    <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                </a>
            </div>

            {{-- Voucher List Section --}}
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                {{-- List Header with Filters --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Voucher Preference Status
                    </h4>
                    <div class="flex items-center gap-2">
                        <button
                            wire:click="setSortOrder('all')"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors
                                {{ $this->sortOrder === 'all'
                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600' }}"
                        >
                            All ({{ count($this->getVoucherList()) }})
                        </button>
                        <button
                            wire:click="setSortOrder('collected')"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors
                                {{ $this->sortOrder === 'collected'
                                    ? 'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600' }}"
                        >
                            Collected
                        </button>
                        <button
                            wire:click="setSortOrder('pending')"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition-colors
                                {{ $this->sortOrder === 'pending'
                                    ? 'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600' }}"
                        >
                            Pending
                        </button>
                    </div>
                </div>

                {{-- Voucher List --}}
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($vouchers as $voucher)
                        <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                @if($voucher['preference_status'] === 'collected')
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-success-100 dark:bg-success-900/30 flex items-center justify-center">
                                        <x-heroicon-o-check class="h-4 w-4 text-success-600 dark:text-success-400" />
                                    </div>
                                @else
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-warning-100 dark:bg-warning-900/30 flex items-center justify-center">
                                        <x-heroicon-o-clock class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                                    </div>
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $voucher['customer_name'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Voucher: {{ Str::limit($voucher['voucher_id'], 8, '...') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                @if($voucher['preference_status'] === 'collected')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400">
                                        Collected
                                    </span>
                                    @if($voucher['format_preference'])
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Format: {{ $voucher['format_preference'] }}
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400">
                                        Pending
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <x-heroicon-o-inbox class="h-8 w-8 mx-auto text-gray-400" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                No vouchers found matching the current filter.
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($totalPages > 1)
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Page {{ $this->currentPage }} of {{ $totalPages }}
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="previousPage"
                                class="px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                {{ $this->currentPage <= 1 ? 'disabled' : '' }}
                            >
                                Previous
                            </button>
                            <button
                                wire:click="nextPage"
                                class="px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                {{ $this->currentPage >= $totalPages ? 'disabled' : '' }}
                            >
                                Next
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Help Text --}}
            <div class="flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400">
                <x-heroicon-o-information-circle class="h-4 w-4 flex-shrink-0 mt-0.5" />
                <p>
                    Customer preferences are collected via the external customer portal. Contact the customer success team if you need to send reminder emails to customers with pending preferences.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
