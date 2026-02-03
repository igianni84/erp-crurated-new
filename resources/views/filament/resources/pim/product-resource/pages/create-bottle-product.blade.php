<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                Choose Creation Method
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Select how you want to create the bottle product.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Liv-ex Import Card --}}
            <div class="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 hover:border-primary-500 dark:hover:border-primary-500 transition-colors cursor-pointer group">
                <a href="{{ route('filament.admin.resources.pim.products.import-livex') }}" class="absolute inset-0 z-10"></a>
                <div class="flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center mb-4 group-hover:bg-primary-100 dark:group-hover:bg-primary-500/20 transition-colors">
                        <x-heroicon-o-cloud-arrow-down class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Import from Liv-ex
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Search and import product data from the Liv-ex database for accurate, standardized information.
                    </p>
                    <ul class="text-left text-sm text-gray-600 dark:text-gray-400 space-y-2 w-full">
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" />
                            <span>Search by LWIN or wine name</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" />
                            <span>Pre-filled accurate data</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" />
                            <span>Automatic media import</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" />
                            <span>Recommended for standard wines</span>
                        </li>
                    </ul>
                    <div class="mt-6 w-full">
                        <span class="inline-flex items-center justify-center w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors">
                            <x-heroicon-m-cloud-arrow-down class="w-5 h-5 mr-2" />
                            Import from Liv-ex
                        </span>
                    </div>
                </div>
            </div>

            {{-- Manual Creation Card --}}
            <div class="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 hover:border-gray-500 dark:hover:border-gray-500 transition-colors cursor-pointer group">
                <a href="{{ \App\Filament\Resources\Pim\ProductResource::getUrl('create-manual') }}" class="absolute inset-0 z-10"></a>
                <div class="flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4 group-hover:bg-gray-200 dark:group-hover:bg-gray-600 transition-colors">
                        <x-heroicon-o-pencil-square class="w-8 h-8 text-gray-600 dark:text-gray-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Create Manually
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Enter all product information manually when not available in Liv-ex.
                    </p>
                    <ul class="text-left text-sm text-gray-600 dark:text-gray-400 space-y-2 w-full">
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-gray-600 dark:text-gray-400 flex-shrink-0" />
                            <span>Full control over all fields</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-gray-600 dark:text-gray-400 flex-shrink-0" />
                            <span>For rare or unique wines</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-gray-600 dark:text-gray-400 flex-shrink-0" />
                            <span>Upload custom media</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-gray-600 dark:text-gray-400 flex-shrink-0" />
                            <span>When Liv-ex data unavailable</span>
                        </li>
                    </ul>
                    <div class="mt-6 w-full">
                        <span class="inline-flex items-center justify-center w-full px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors">
                            <x-heroicon-m-pencil-square class="w-5 h-5 mr-2" />
                            Create Manually
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
