<x-filament-panels::page>
    @php
        $calendarDays = $this->getCalendarDays();
        $channelOptions = $this->getChannelOptions();
        $eventTypeOptions = $this->getEventTypeOptions();
        $eventCounts = $this->getEventCounts();
    @endphp

    {{-- Page Header --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <x-heroicon-o-calendar-days class="h-6 w-6 text-primary-500 mr-3" />
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Commercial Calendar</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        View Price Book validity periods, Offer validity periods, and scheduled Pricing Policy executions.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ \App\Filament\Pages\CommercialOverview::getUrl() }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-1" />
                    Back to Overview
                </a>
            </div>
        </div>
    </div>

    {{-- Event Type Legend --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-6">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Event Types:</span>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                <span class="text-sm text-gray-600 dark:text-gray-400">Price Books ({{ $eventCounts['price_book'] }})</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                <span class="text-sm text-gray-600 dark:text-gray-400">Offers ({{ $eventCounts['offer'] }})</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-indigo-400"></span>
                <span class="text-sm text-gray-600 dark:text-gray-400">Scheduled Policies ({{ $eventCounts['policy'] }})</span>
            </div>
        </div>
    </div>

    {{-- Calendar Controls --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            {{-- Navigation --}}
            <div class="flex items-center gap-2">
                <button
                    wire:click="previousPeriod"
                    class="inline-flex items-center justify-center p-2 text-gray-500 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <x-heroicon-o-chevron-left class="w-5 h-5" />
                </button>
                <button
                    wire:click="goToToday"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Today
                </button>
                <button
                    wire:click="nextPeriod"
                    class="inline-flex items-center justify-center p-2 text-gray-500 rounded-lg hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                >
                    <x-heroicon-o-chevron-right class="w-5 h-5" />
                </button>
                <span class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $this->getCurrentPeriodLabel() }}
                </span>
            </div>

            {{-- View Toggle & Filters --}}
            <div class="flex flex-wrap items-center gap-4">
                {{-- View Toggle --}}
                <div class="inline-flex rounded-lg shadow-sm">
                    <button
                        wire:click="setCalendarView('month')"
                        class="px-3 py-1.5 text-sm font-medium rounded-l-lg border {{ $calendarView === 'month' ? 'bg-primary-500 text-white border-primary-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600' }}"
                    >
                        Month
                    </button>
                    <button
                        wire:click="setCalendarView('week')"
                        class="px-3 py-1.5 text-sm font-medium rounded-r-lg border-t border-r border-b {{ $calendarView === 'week' ? 'bg-primary-500 text-white border-primary-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600' }}"
                    >
                        Week
                    </button>
                </div>

                {{-- Event Type Filter --}}
                <select
                    wire:model.live="eventTypeFilter"
                    class="text-sm border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                >
                    <option value="">All Event Types</option>
                    @foreach($eventTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                {{-- Channel Filter --}}
                <select
                    wire:model.live="channelFilter"
                    class="text-sm border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                >
                    <option value="">All Channels</option>
                    @foreach($channelOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Calendar Grid --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        {{-- Day Headers --}}
        <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <div class="px-2 py-3 text-center text-sm font-semibold text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800">
                    {{ $day }}
                </div>
            @endforeach
        </div>

        {{-- Calendar Days --}}
        <div class="grid grid-cols-7 {{ $calendarView === 'week' ? '' : 'divide-y divide-gray-200 dark:divide-gray-700' }}">
            @foreach($calendarDays as $index => $day)
                @php
                    $rowStart = $index % 7 === 0;
                @endphp
                <div class="min-h-[120px] p-2 border-r border-gray-200 dark:border-gray-700 {{ !$day['isCurrentMonth'] ? 'bg-gray-50 dark:bg-gray-800/50' : '' }} {{ $day['isToday'] ? 'bg-primary-50 dark:bg-primary-900/20' : '' }} {{ $index % 7 === 6 ? 'border-r-0' : '' }}">
                    {{-- Day Number --}}
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium {{ !$day['isCurrentMonth'] ? 'text-gray-400 dark:text-gray-500' : ($day['isToday'] ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white') }}">
                            {{ $day['date']->format('j') }}
                        </span>
                        @if($day['isToday'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-400/20 dark:text-primary-300">
                                Today
                            </span>
                        @endif
                    </div>

                    {{-- Events --}}
                    <div class="space-y-1 overflow-y-auto max-h-[80px]">
                        @foreach(array_slice($day['events'], 0, 3) as $event)
                            <a
                                href="{{ $event['url'] }}"
                                class="block px-1.5 py-0.5 text-xs font-medium text-white rounded truncate hover:opacity-80 transition-opacity"
                                style="background-color: {{ $event['color'] }}"
                                title="{{ $event['title'] }}{{ $event['channel_name'] ? ' - ' . $event['channel_name'] : '' }}"
                            >
                                @if($event['type'] === 'price_book')
                                    <x-heroicon-o-book-open class="inline w-3 h-3 mr-0.5" />
                                @elseif($event['type'] === 'offer')
                                    <x-heroicon-o-tag class="inline w-3 h-3 mr-0.5" />
                                @else
                                    <x-heroicon-o-cog-6-tooth class="inline w-3 h-3 mr-0.5" />
                                @endif
                                {{ Str::limit($event['title'], 15) }}
                            </a>
                        @endforeach
                        @if(count($day['events']) > 3)
                            <div class="text-xs text-gray-500 dark:text-gray-400 px-1">
                                +{{ count($day['events']) - 3 }} more
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Event List for Current Period --}}
    @php
        $allEvents = $this->getEvents();
        $sortedEvents = collect($allEvents)->sortBy('start')->values()->all();
    @endphp
    @if(count($sortedEvents) > 0)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6">
            <div class="fi-section-header-ctn border-b border-gray-200 dark:border-white/10 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white flex items-center">
                    <x-heroicon-o-list-bullet class="h-5 w-5 mr-2 text-gray-500" />
                    Events in This Period ({{ count($sortedEvents) }})
                </h3>
            </div>
            <div class="fi-section-content divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($sortedEvents as $event)
                    <a href="{{ $event['url'] }}" class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-3 h-3 rounded-full mr-3" style="background-color: {{ $event['color'] }}"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $event['title'] }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($event['start'])->format('M d, Y') }}
                                    @if($event['end'] && $event['end'] !== $event['start'])
                                        - {{ \Carbon\Carbon::parse($event['end'])->format('M d, Y') }}
                                    @endif
                                    @if($event['channel_name'])
                                        <span class="text-gray-400 dark:text-gray-500">|</span> {{ $event['channel_name'] }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                {{ $event['type'] === 'price_book' ? 'bg-green-100 text-green-700 dark:bg-green-400/20 dark:text-green-300' : '' }}
                                {{ $event['type'] === 'offer' ? 'bg-blue-100 text-blue-700 dark:bg-blue-400/20 dark:text-blue-300' : '' }}
                                {{ $event['type'] === 'policy' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/20 dark:text-indigo-300' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $event['type'])) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ ucfirst($event['status']) }}
                            </span>
                            <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6 p-8">
            <div class="text-center">
                <x-heroicon-o-calendar-days class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No events in this period</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Try adjusting the filters or navigating to a different date range</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>
