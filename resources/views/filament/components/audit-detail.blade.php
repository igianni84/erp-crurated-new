@php
    /** @var \App\Models\AuditLog $record */
    $oldValues = $record->old_values ?? [];
    $newValues = $record->new_values ?? [];
    $changedFields = array_keys(array_merge($oldValues, $newValues));
@endphp

<div class="space-y-6">
    {{-- Event Summary --}}
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Date/Time</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $record->created_at?->format('M j, Y H:i:s') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">User</dt>
                <dd class="mt-1 text-gray-900 dark:text-white">{{ $record->user?->name ?? 'System' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Entity ID</dt>
                <dd class="mt-1 text-gray-900 dark:text-white font-mono text-xs">{{ $record->auditable_id }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Event</dt>
                <dd class="mt-1">
                    <span @class([
                        'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium',
                        'bg-success-100 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $record->getEventColor() === 'success',
                        'bg-info-100 text-info-700 dark:bg-info-400/10 dark:text-info-400' => $record->getEventColor() === 'info',
                        'bg-warning-100 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $record->getEventColor() === 'warning',
                        'bg-danger-100 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => $record->getEventColor() === 'danger',
                        'bg-gray-100 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400' => $record->getEventColor() === 'gray',
                    ])>
                        <x-dynamic-component :component="$record->getEventIcon()" class="h-4 w-4" />
                        {{ $record->getEventLabel() }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    {{-- Changes Table --}}
    @if(count($changedFields) > 0)
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Changes</h4>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Field</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Old Value</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">New Value</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($changedFields as $field)
                            @php
                                $oldValue = $oldValues[$field] ?? null;
                                $newValue = $newValues[$field] ?? null;
                                $hasChanged = $oldValue !== $newValue;
                            @endphp
                            <tr @class(['bg-yellow-50 dark:bg-yellow-400/5' => $hasChanged])>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ str_replace('_', ' ', ucfirst($field)) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    @if($oldValue === null)
                                        <span class="text-gray-400 dark:text-gray-500 italic">null</span>
                                    @elseif(is_array($oldValue))
                                        <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ json_encode($oldValue) }}</code>
                                    @elseif(is_bool($oldValue))
                                        <span @class(['text-success-600 dark:text-success-400' => $oldValue, 'text-danger-600 dark:text-danger-400' => !$oldValue])>
                                            {{ $oldValue ? 'true' : 'false' }}
                                        </span>
                                    @else
                                        {{ $oldValue }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    @if($newValue === null)
                                        <span class="text-gray-400 dark:text-gray-500 italic">null</span>
                                    @elseif(is_array($newValue))
                                        <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ json_encode($newValue) }}</code>
                                    @elseif(is_bool($newValue))
                                        <span @class(['text-success-600 dark:text-success-400' => $newValue, 'text-danger-600 dark:text-danger-400' => !$newValue])>
                                            {{ $newValue ? 'true' : 'false' }}
                                        </span>
                                    @else
                                        {{ $newValue }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">No detailed change information available for this event.</p>
        </div>
    @endif
</div>
