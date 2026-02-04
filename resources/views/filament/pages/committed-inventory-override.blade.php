<x-filament-panels::page>
    {{-- Warning Banner --}}
    <div class="mb-6 p-4 rounded-lg bg-danger-100 dark:bg-danger-900/40 border-2 border-danger-500">
        <div class="flex">
            <div class="flex-shrink-0">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-danger-600 dark:text-danger-400" />
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-bold text-danger-800 dark:text-danger-200">
                    EXCEPTIONAL OPERATION
                </h3>
                <p class="text-sm text-danger-700 dark:text-danger-300 mt-1">
                    This page allows <strong>COMMITTED</strong> inventory consumption override.
                    This is an <strong>exceptional</strong> operation that bypasses normal inventory protection.
                    All overrides are logged and require Finance & Ops review.
                </p>
            </div>
        </div>
    </div>

    {{-- Form with Wizard --}}
    <form wire:submit="create">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
