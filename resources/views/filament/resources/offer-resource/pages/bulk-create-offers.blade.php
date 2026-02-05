<x-filament-panels::page>
    <div wire:loading.delay.long wire:target="create" class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-8 max-w-md mx-auto text-center">
            <div class="animate-spin w-12 h-12 border-4 border-primary-200 border-t-primary-600 rounded-full mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Creating Offers</h3>
            <p class="text-gray-600 dark:text-gray-400">Please wait while your offers are being created...</p>
        </div>
    </div>

    {{ $this->form }}
</x-filament-panels::page>
