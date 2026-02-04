<x-filament-panels::page>
    {{-- Info Banner --}}
    <div class="mb-6 p-4 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800">
        <div class="flex">
            <div class="flex-shrink-0">
                <x-heroicon-o-information-circle class="h-5 w-5 text-info-600 dark:text-info-400" />
            </div>
            <div class="ml-3">
                <p class="text-sm text-info-700 dark:text-info-300">
                    <strong>Internal Transfer</strong> - Move inventory between locations within your organization.
                    Follow the wizard steps to select the source location, items to transfer, and destination.
                    All transfers create <strong>immutable movement records</strong> for audit purposes.
                </p>
            </div>
        </div>
    </div>

    {{-- Form with Wizard --}}
    <form wire:submit="create">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
