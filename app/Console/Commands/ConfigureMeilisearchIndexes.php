<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;

class ConfigureMeilisearchIndexes extends Command
{
    protected $signature = 'search:configure-indexes';

    protected $description = 'Configure Meilisearch index settings (filterable, sortable, searchable attributes)';

    public function handle(): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->warn('Scout driver is not meilisearch — skipping index configuration.');

            return self::SUCCESS;
        }

        $client = app(Client::class);
        $prefix = config('scout.prefix', '');

        $this->configureWineMasters($client, $prefix);
        $this->configureWineVariants($client, $prefix);
        $this->configureSellableSkus($client, $prefix);

        $this->info('All Meilisearch indexes configured.');

        return self::SUCCESS;
    }

    protected function configureWineMasters(Client $client, string $prefix): void
    {
        $index = $client->index($prefix.'wine_masters');

        $index->updateSearchableAttributes([
            'name',
            'producer_name',
            'appellation_name',
            'region_name',
            'country_name',
            'classification',
            'description',
            'liv_ex_code',
        ]);

        $index->updateFilterableAttributes([
            'country_name',
            'region_name',
            'producer_name',
            'appellation_name',
        ]);

        $index->updateSortableAttributes([
            'name',
        ]);

        $this->info('Configured wine_masters index.');
    }

    protected function configureWineVariants(Client $client, string $prefix): void
    {
        $index = $client->index($prefix.'wine_variants');

        $index->updateSearchableAttributes([
            'wine_name',
            'producer_name',
            'appellation_name',
            'region_name',
            'country_name',
            'classification',
            'description',
            'lwin_code',
            'format_names',
        ]);

        $index->updateFilterableAttributes([
            'country_name',
            'region_name',
            'appellation_name',
            'producer_name',
            'vintage_year',
            'format_names',
            'lifecycle_status',
            'has_active_skus',
        ]);

        $index->updateSortableAttributes([
            'vintage_year',
            'wine_name',
            'created_at',
        ]);

        $this->info('Configured wine_variants index.');
    }

    protected function configureSellableSkus(Client $client, string $prefix): void
    {
        $index = $client->index($prefix.'sellable_skus');

        $index->updateSearchableAttributes([
            'sku_code',
            'barcode',
            'wine_name',
            'producer_name',
            'format_name',
        ]);

        $index->updateFilterableAttributes([
            'lifecycle_status',
            'vintage_year',
        ]);

        $index->updateSortableAttributes([
            'sku_code',
            'vintage_year',
        ]);

        $this->info('Configured sellable_skus index.');
    }
}
