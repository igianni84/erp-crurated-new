<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Supplier/Producer Configuration
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Configure default values for Purchase Orders and Bottling Instructions.
                    </p>
                </div>
            </div>
        </div>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                <a
                    href="{{ route('filament.admin.resources.customer.parties.view', ['record' => $this->record->id]) }}"
                    class="fi-btn fi-btn-color-gray fi-btn-size-md fi-color-custom fi-color-gray inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none disabled:pointer-events-none disabled:opacity-70 px-4 py-2 text-sm text-gray-950 bg-white border border-gray-200 hover:bg-gray-50 dark:text-white dark:bg-white/5 dark:border-gray-700 dark:hover:bg-white/10"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="fi-btn fi-btn-color-primary fi-btn-size-md fi-color-custom fi-color-primary inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none disabled:pointer-events-none disabled:opacity-70 px-4 py-2 text-sm text-white bg-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                >
                    <x-heroicon-m-check class="w-5 h-5" />
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
