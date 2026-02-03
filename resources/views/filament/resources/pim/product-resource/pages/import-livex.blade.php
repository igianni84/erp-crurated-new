<x-filament-panels::page>
    <div class="max-w-2xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
            <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-cloud-arrow-down class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    Liv-ex Import
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    This feature will allow you to search and import product data from Liv-ex.
                </p>
                <div class="bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20 rounded-lg p-4">
                    <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200">
                        <x-heroicon-m-information-circle class="w-5 h-5 flex-shrink-0" />
                        <p class="text-sm">
                            Full Liv-ex integration coming soon. For now, please use manual creation.
                        </p>
                    </div>
                </div>
                <div class="mt-6">
                    <a href="{{ \App\Filament\Resources\Pim\WineVariantResource::getUrl('create') }}"
                       class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors">
                        <x-heroicon-m-pencil-square class="w-5 h-5 mr-2" />
                        Create Manually Instead
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
