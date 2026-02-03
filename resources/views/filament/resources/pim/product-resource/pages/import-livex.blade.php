<x-filament-panels::page>
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Search Section --}}
        @if (!$showConfirmation)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Search Liv-ex Database</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Search by LWIN code, wine name, producer, or appellation</p>
                    </div>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="e.g., Sassicaia, LWIN1100001, Château Margaux..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-primary-500 pl-10"
                    />
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    @if(strlen($searchQuery) > 0)
                        <button
                            wire:click="$set('searchQuery', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    @endif
                </div>

                @if(strlen($searchQuery) >= 2)
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Found {{ count($this->searchResults) }} result(s)
                    </div>
                @elseif(strlen($searchQuery) > 0)
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Type at least 2 characters to search
                    </div>
                @endif
            </div>

            {{-- Search Results --}}
            @if(count($this->searchResults) > 0)
                <div class="space-y-3">
                    @foreach($this->searchResults as $result)
                        <div
                            wire:click="selectWine('{{ $result['lwin'] }}')"
                            class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4 cursor-pointer hover:border-primary-500 hover:shadow-md transition-all"
                        >
                            <div class="flex items-start gap-4">
                                {{-- Wine Image --}}
                                <div class="w-16 h-20 flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                    @if($result['image_url'])
                                        <img src="{{ $result['image_url'] }}" alt="{{ $result['name'] }}" class="w-full h-full object-cover" />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <x-heroicon-o-photo class="w-8 h-8 text-gray-400" />
                                        </div>
                                    @endif
                                </div>

                                {{-- Wine Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                                {{ $result['name'] }} {{ $result['vintage'] }}
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $result['producer'] }}
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400">
                                            <x-heroicon-m-cloud-arrow-down class="w-3 h-3 mr-1" />
                                            Liv-ex
                                        </span>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $result['appellation'] }}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $result['country'] }} - {{ $result['region'] }}
                                        </span>
                                        @if($result['classification'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                {{ $result['classification'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                        LWIN: {{ $result['lwin'] }}
                                        @if($result['alcohol'])
                                            &bull; {{ $result['alcohol'] }}% ABV
                                        @endif
                                        @if($result['drinking_window_start'] && $result['drinking_window_end'])
                                            &bull; Drink {{ $result['drinking_window_start'] }}-{{ $result['drinking_window_end'] }}
                                        @endif
                                    </div>
                                </div>

                                {{-- Select Arrow --}}
                                <div class="flex-shrink-0 text-gray-400">
                                    <x-heroicon-o-chevron-right class="w-5 h-5" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif(strlen($searchQuery) >= 2)
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3">
                        <x-heroicon-o-magnifying-glass class="w-6 h-6 text-gray-400" />
                    </div>
                    <p class="text-gray-600 dark:text-gray-400">No wines found matching "{{ $searchQuery }}"</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Try a different search term or create the wine manually</p>
                    <a href="{{ \App\Filament\Resources\Pim\WineVariantResource::getUrl('create') }}"
                       class="inline-flex items-center justify-center px-4 py-2 mt-4 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors">
                        <x-heroicon-m-pencil-square class="w-5 h-5 mr-2" />
                        Create Manually
                    </a>
                </div>
            @else
                {{-- Empty State / Instructions --}}
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-3">Quick Tips</h3>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start gap-2">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" />
                            <span>Search by <strong>LWIN code</strong> for exact matches (e.g., LWIN1100001)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" />
                            <span>Search by <strong>wine name</strong> (e.g., Sassicaia, Opus One)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" />
                            <span>Search by <strong>producer name</strong> (e.g., Château Margaux)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5" />
                            <span>Search by <strong>appellation</strong> (e.g., Pauillac, Barolo)</span>
                        </li>
                    </ul>
                </div>
            @endif
        @else
            {{-- Confirmation Section --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Header --}}
                <div class="bg-primary-50 dark:bg-primary-500/10 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-500/20 flex items-center justify-center">
                            <x-heroicon-o-cloud-arrow-down class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Import</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Review the wine data before importing</p>
                        </div>
                    </div>
                </div>

                {{-- Wine Preview --}}
                <div class="p-6">
                    <div class="flex items-start gap-6">
                        {{-- Wine Image --}}
                        <div class="w-24 h-32 flex-shrink-0 rounded-lg bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            @if($selectedWine['image_url'] ?? null)
                                <img src="{{ $selectedWine['image_url'] }}" alt="{{ $selectedWine['name'] }}" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <x-heroicon-o-photo class="w-10 h-10 text-gray-400" />
                                </div>
                            @endif
                        </div>

                        {{-- Wine Details --}}
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ $selectedWine['name'] ?? '' }} {{ $selectedWine['vintage'] ?? '' }}
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ $selectedWine['producer'] ?? '' }}
                            </p>

                            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-500">LWIN Code</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['lwin'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-500">Appellation</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['appellation'] ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-500">Country / Region</dt>
                                    <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['country'] ?? '' }} - {{ $selectedWine['region'] ?? '' }}</dd>
                                </div>
                                @if($selectedWine['classification'] ?? null)
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-500">Classification</dt>
                                        <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['classification'] }}</dd>
                                    </div>
                                @endif
                                @if($selectedWine['alcohol'] ?? null)
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-500">Alcohol</dt>
                                        <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['alcohol'] }}%</dd>
                                    </div>
                                @endif
                                @if(($selectedWine['drinking_window_start'] ?? null) && ($selectedWine['drinking_window_end'] ?? null))
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-500">Drinking Window</dt>
                                        <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedWine['drinking_window_start'] }} - {{ $selectedWine['drinking_window_end'] }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if($selectedWine['description'] ?? null)
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $selectedWine['description'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Locked Fields Warning --}}
                <div class="px-6 pb-6">
                    <div class="bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <x-heroicon-m-lock-closed class="w-5 h-5 text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <h4 class="font-medium text-warning-800 dark:text-warning-200">Locked Fields</h4>
                                <p class="text-sm text-warning-700 dark:text-warning-300 mt-1">
                                    The following fields will be locked after import and can only be overridden by a Manager or Admin:
                                </p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($this->getLockedFields() as $field)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300">
                                            {{ ucfirst(str_replace('_', ' ', $field)) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
                    <button
                        wire:click="cancelSelection"
                        class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                    >
                        <x-heroicon-m-arrow-left class="w-5 h-5 mr-2" />
                        Back to Search
                    </button>
                    <button
                        wire:click="confirmImport"
                        class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
                    >
                        <x-heroicon-m-cloud-arrow-down class="w-5 h-5 mr-2" />
                        Import Wine
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
