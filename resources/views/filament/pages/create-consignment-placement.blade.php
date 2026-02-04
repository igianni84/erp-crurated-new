<x-filament-panels::page>
    {{-- Info Banner --}}
    <div class="mb-6 p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800">
        <div class="flex">
            <div class="flex-shrink-0">
                <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-warning-600 dark:text-warning-400" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-warning-700 dark:text-warning-300">
                    <strong>Consignment Placement</strong> - Place Crurated-owned inventory at a consignee location.
                    <strong>Ownership</strong> remains with Crurated, but <strong>custody</strong> transfers to the consignee.
                    Only Crurated-owned items can be placed in consignment.
                </p>
            </div>
        </div>
    </div>

    {{-- Form with Wizard --}}
    <form wire:submit="create">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
