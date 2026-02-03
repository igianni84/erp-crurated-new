<x-filament-panels::page>
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                Choose Product Category
            </h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Select the type of product you want to create. This choice cannot be changed after creation.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Bottle Product Card --}}
            <div class="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 hover:border-success-500 dark:hover:border-success-500 transition-colors cursor-pointer group">
                <a href="{{ route('filament.admin.resources.pim.products.create-bottle') }}" class="absolute inset-0 z-10"></a>
                <div class="flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-success-50 dark:bg-success-500/10 flex items-center justify-center mb-4 group-hover:bg-success-100 dark:group-hover:bg-success-500/20 transition-colors">
                        <x-heroicon-o-cube class="w-8 h-8 text-success-600 dark:text-success-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Bottle Product
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Standard bottled wine products with specific vintages, formats, and case configurations.
                    </p>
                    <ul class="text-left text-sm text-gray-600 dark:text-gray-400 space-y-2 w-full">
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-success-600 dark:text-success-400 flex-shrink-0" />
                            <span>Physical bottles with barcodes</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-success-600 dark:text-success-400 flex-shrink-0" />
                            <span>Multiple format options (375ml, 750ml, 1500ml...)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-success-600 dark:text-success-400 flex-shrink-0" />
                            <span>Case configurations (OWC, OC, loose)</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-success-600 dark:text-success-400 flex-shrink-0" />
                            <span>Sellable SKUs for commerce</span>
                        </li>
                    </ul>
                    <div class="mt-6 w-full">
                        <span class="inline-flex items-center justify-center w-full px-4 py-2 bg-success-600 hover:bg-success-700 text-white rounded-lg font-medium transition-colors">
                            <x-heroicon-m-plus class="w-5 h-5 mr-2" />
                            Create Bottle Product
                        </span>
                    </div>
                </div>
            </div>

            {{-- Liquid Product Card --}}
            <div class="relative bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 hover:border-info-500 dark:hover:border-info-500 transition-colors cursor-pointer group">
                <a href="{{ \App\Filament\Resources\Pim\LiquidProductResource::getUrl('create') }}" class="absolute inset-0 z-10"></a>
                <div class="flex flex-col items-center text-center">
                    <div class="w-16 h-16 rounded-full bg-info-50 dark:bg-info-500/10 flex items-center justify-center mb-4 group-hover:bg-info-100 dark:group-hover:bg-info-500/20 transition-colors">
                        <x-heroicon-o-beaker class="w-8 h-8 text-info-600 dark:text-info-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Liquid Product
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Pre-bottling wine products sold in bulk before final packaging.
                    </p>
                    <ul class="text-left text-sm text-gray-600 dark:text-gray-400 space-y-2 w-full">
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-info-600 dark:text-info-400 flex-shrink-0" />
                            <span>Bulk wine not yet bottled</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-info-600 dark:text-info-400 flex-shrink-0" />
                            <span>Equivalent unit conversions</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-info-600 dark:text-info-400 flex-shrink-0" />
                            <span>Bottling constraints and rules</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <x-heroicon-m-check class="w-4 h-4 text-info-600 dark:text-info-400 flex-shrink-0" />
                            <span>Serialization tracking option</span>
                        </li>
                    </ul>
                    <div class="mt-6 w-full">
                        <span class="inline-flex items-center justify-center w-full px-4 py-2 bg-info-600 hover:bg-info-700 text-white rounded-lg font-medium transition-colors">
                            <x-heroicon-m-plus class="w-5 h-5 mr-2" />
                            Create Liquid Product
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-m-information-circle class="w-4 h-4 inline-block mr-1" />
                The product category is set at creation and cannot be changed later.
            </p>
        </div>
    </div>
</x-filament-panels::page>
