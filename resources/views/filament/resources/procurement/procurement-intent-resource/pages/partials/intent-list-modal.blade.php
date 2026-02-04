<div class="space-y-4">
    @if($intents->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800">
            <x-heroicon-o-document-text class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No intents found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no procurement intents for this product.</p>
        </div>
    @else
        {{-- Summary Stats --}}
        <div class="grid grid-cols-4 gap-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Total Intents</div>
                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $intents->count() }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                <div class="text-xs text-gray-500 dark:text-gray-400">Total Quantity</div>
                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format($intents->sum('quantity')) }}</div>
            </div>
            <div class="rounded-lg bg-warning-50 p-3 dark:bg-warning-900/20">
                <div class="text-xs text-warning-600 dark:text-warning-400">Draft</div>
                <div class="mt-1 text-lg font-semibold text-warning-700 dark:text-warning-300">{{ $intents->where('status', \App\Enums\Procurement\ProcurementIntentStatus::Draft)->count() }}</div>
            </div>
            <div class="rounded-lg bg-success-50 p-3 dark:bg-success-900/20">
                <div class="text-xs text-success-600 dark:text-success-400">Approved</div>
                <div class="mt-1 text-lg font-semibold text-success-700 dark:text-success-300">{{ $intents->where('status', \App\Enums\Procurement\ProcurementIntentStatus::Approved)->count() }}</div>
            </div>
        </div>

        {{-- Intent List --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Intent ID</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Trigger</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sourcing</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Updated</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach($intents as $intent)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-400" title="{{ $intent->id }}">
                                    {{ Str::limit($intent->id, 8, '...') }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                                    {{ number_format($intent->quantity) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @switch($intent->trigger_type->value)
                                        @case('voucher_driven')
                                            bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300
                                            @break
                                        @case('allocation_driven')
                                            bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                            @break
                                        @case('strategic')
                                            bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300
                                            @break
                                        @case('contractual')
                                            bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                    @endswitch
                                ">
                                    {{ $intent->trigger_type->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @switch($intent->sourcing_model->value)
                                        @case('purchase')
                                            bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300
                                            @break
                                        @case('passive_consignment')
                                            bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300
                                            @break
                                        @case('third_party_custody')
                                            bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                    @endswitch
                                ">
                                    {{ $intent->sourcing_model->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @switch($intent->status->value)
                                        @case('draft')
                                            bg-warning-100 text-warning-800 dark:bg-warning-900/30 dark:text-warning-300
                                            @break
                                        @case('approved')
                                            bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-300
                                            @break
                                        @case('executed')
                                            bg-info-100 text-info-800 dark:bg-info-900/30 dark:text-info-300
                                            @break
                                        @case('closed')
                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                    @endswitch
                                ">
                                    {{ $intent->status->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ $intent->updated_at->format('M d, Y') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <a href="{{ route('filament.admin.resources.procurement/intents.view', $intent) }}"
                                   class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                                   target="_blank">
                                    <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" />
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
