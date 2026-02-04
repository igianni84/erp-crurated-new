<x-filament-panels::page>
    {{-- Info Banner --}}
    <div class="mb-6 p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800">
        <div class="flex">
            <div class="flex-shrink-0">
                <x-heroicon-o-fire class="h-5 w-5 text-danger-600 dark:text-danger-400" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-danger-700 dark:text-danger-300">
                    <strong>Event Consumption</strong> - Record consumption of inventory for events.
                    Only <strong>Crurated-owned</strong> bottles in <strong>stored</strong> state can be consumed.
                    <strong>Committed bottles</strong> (reserved for customer fulfillment) are blocked.
                    This action is <strong>irreversible</strong> - consumed bottles cannot be recovered.
                </p>
            </div>
        </div>
    </div>

    {{-- Form with Wizard --}}
    <form wire:submit="create">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
