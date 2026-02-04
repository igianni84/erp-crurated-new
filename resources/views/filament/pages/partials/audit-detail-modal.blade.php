<div class="space-y-4">
    {{-- Event Summary --}}
    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->created_at?->format('Y-m-d H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->user?->name ?? 'System' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Entity Type</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    @php
                        $entityLabel = match($record->auditable_type) {
                            \App\Models\Inventory\SerializedBottle::class => 'Serialized Bottle',
                            \App\Models\Inventory\InventoryCase::class => 'Case',
                            \App\Models\Inventory\InboundBatch::class => 'Inbound Batch',
                            default => class_basename($record->auditable_type),
                        };
                    @endphp
                    {{ $entityLabel }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Entity ID</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono text-xs break-all">{{ $record->auditable_id }}</dd>
            </div>
        </dl>
    </div>

    {{-- Changes Section --}}
    @php
        $oldValues = $record->old_values ?? [];
        $newValues = $record->new_values ?? [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
    @endphp

    @if(count($allFields) > 0)
        <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Changes</h4>
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Field</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Old Value</th>
                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">New Value</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($allFields as $field)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ $field }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    @php
                                        $oldVal = $oldValues[$field] ?? null;
                                        if (is_array($oldVal)) {
                                            $displayOld = json_encode($oldVal, JSON_UNESCAPED_UNICODE);
                                        } elseif (is_bool($oldVal)) {
                                            $displayOld = $oldVal ? 'true' : 'false';
                                        } elseif ($oldVal === null) {
                                            $displayOld = null;
                                        } else {
                                            $displayOld = (string)$oldVal;
                                        }
                                    @endphp
                                    @if($displayOld !== null)
                                        <span class="line-through text-danger-600 dark:text-danger-400">{{ $displayOld }}</span>
                                    @else
                                        <span class="italic text-gray-400">null</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    @php
                                        $newVal = $newValues[$field] ?? null;
                                        if (is_array($newVal)) {
                                            $displayNew = json_encode($newVal, JSON_UNESCAPED_UNICODE);
                                        } elseif (is_bool($newVal)) {
                                            $displayNew = $newVal ? 'true' : 'false';
                                        } elseif ($newVal === null) {
                                            $displayNew = null;
                                        } else {
                                            $displayNew = (string)$newVal;
                                        }
                                    @endphp
                                    @if($displayNew !== null)
                                        <span class="text-success-600 dark:text-success-400 font-medium">{{ $displayNew }}</span>
                                    @else
                                        <span class="italic text-gray-400">null</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">No field changes recorded for this event.</p>
        </div>
    @endif

    {{-- Immutability Notice --}}
    <div class="p-3 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
        <div class="flex items-center">
            <x-heroicon-o-shield-check class="h-4 w-4 text-info-600 dark:text-info-400 mr-2" />
            <p class="text-xs text-info-700 dark:text-info-300">
                This audit record is immutable and cannot be modified or deleted. It serves as a permanent compliance record.
            </p>
        </div>
    </div>
</div>
