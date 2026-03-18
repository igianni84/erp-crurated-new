<?php

namespace Tests\Feature\Services\Commercial;

use App\Enums\Commercial\PriceSource;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Services\Commercial\PriceBookCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FilamentTestHelpers;
use Tests\TestCase;

class PriceBookCsvImportServiceTest extends TestCase
{
    use FilamentTestHelpers;
    use RefreshDatabase;

    private PriceBookCsvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentTestHelpers();
        $this->service = app(PriceBookCsvImportService::class);
    }

    // --- parseAndValidate ---

    public function test_parse_valid_csv(): void
    {
        $pimStack1 = $this->createPimStack();
        $pimStack2 = $this->createPimStack();
        $pimStack3 = $this->createPimStack();

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$pimStack1['sellable_sku']->id, '99.99'],
            [$pimStack2['sellable_sku']->id, '150.00'],
            [$pimStack3['sellable_sku']->id, '42.50'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertCount(3, $result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($pimStack1['sellable_sku']->id, $result['valid'][0]['sellable_sku_id']);
        $this->assertEquals('99.99', $result['valid'][0]['base_price']);
    }

    public function test_rejects_invalid_uuid_format(): void
    {
        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            ['not-a-uuid', '99.99'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('sellable_sku_id', $result['errors'][0]['field']);
        $this->assertStringContainsString('Invalid UUID', $result['errors'][0]['message']);
    }

    public function test_rejects_nonexistent_sku(): void
    {
        $fakeUuid = (string) Str::uuid();

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$fakeUuid, '99.99'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('sellable_sku_id', $result['errors'][0]['field']);
        $this->assertStringContainsString('SKU not found', $result['errors'][0]['message']);
    }

    public function test_rejects_negative_price(): void
    {
        $pimStack = $this->createPimStack();

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$pimStack['sellable_sku']->id, '-10.00'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('base_price', $result['errors'][0]['field']);
        $this->assertStringContainsString('greater than zero', $result['errors'][0]['message']);
    }

    public function test_rejects_zero_price(): void
    {
        $pimStack = $this->createPimStack();

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$pimStack['sellable_sku']->id, '0.00'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('base_price', $result['errors'][0]['field']);
    }

    public function test_rejects_non_numeric_price(): void
    {
        $pimStack = $this->createPimStack();

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$pimStack['sellable_sku']->id, 'abc'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('base_price', $result['errors'][0]['field']);
        $this->assertStringContainsString('not numeric', $result['errors'][0]['message']);
    }

    public function test_rejects_duplicate_skus_in_csv(): void
    {
        $pimStack = $this->createPimStack();
        $skuId = $pimStack['sellable_sku']->id;

        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
            [$skuId, '99.99'],
            [$skuId, '150.00'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Duplicate SKU', $result['errors'][0]['message']);
    }

    public function test_rejects_wrong_headers(): void
    {
        $csv = $this->createCsvFile([
            ['sku_id', 'price'],
            ['some-id', '99.99'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('header', $result['errors'][0]['field']);
        $this->assertStringContainsString('Invalid headers', $result['errors'][0]['message']);
    }

    public function test_handles_empty_csv(): void
    {
        $csv = $this->createCsvFile([
            ['sellable_sku_id', 'base_price'],
        ]);

        $result = $this->service->parseAndValidate($csv);

        $this->assertEmpty($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('no data rows', $result['errors'][0]['message']);
    }

    // --- createEntries ---

    public function test_create_entries_happy_path(): void
    {
        $pimStack1 = $this->createPimStack();
        $pimStack2 = $this->createPimStack();
        $priceBook = PriceBook::factory()->create();

        $validRows = [
            ['sellable_sku_id' => $pimStack1['sellable_sku']->id, 'base_price' => '99.99'],
            ['sellable_sku_id' => $pimStack2['sellable_sku']->id, 'base_price' => '150.00'],
        ];

        $count = $this->service->createEntries($priceBook, $validRows);

        $this->assertEquals(2, $count);
        $this->assertDatabaseCount('price_book_entries', 2);
        $this->assertDatabaseHas('price_book_entries', [
            'price_book_id' => $priceBook->id,
            'sellable_sku_id' => $pimStack1['sellable_sku']->id,
            'base_price' => '99.99',
        ]);
    }

    public function test_create_entries_uses_manual_source(): void
    {
        $pimStack = $this->createPimStack();
        $priceBook = PriceBook::factory()->create();

        $this->service->createEntries($priceBook, [
            ['sellable_sku_id' => $pimStack['sellable_sku']->id, 'base_price' => '50.00'],
        ]);

        $entry = PriceBookEntry::query()->first();
        $this->assertNotNull($entry);
        /** @var PriceBookEntry $entry */
        $this->assertEquals(PriceSource::Manual, $entry->source);
        $this->assertNull($entry->policy_id);
    }

    // --- downloadTemplate ---

    public function test_download_template_returns_csv(): void
    {
        $response = $this->service->downloadTemplate();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('pricebook_import_template.csv', (string) $response->headers->get('Content-Disposition'));

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertIsString($content);
        $this->assertStringContainsString('sellable_sku_id', $content);
        $this->assertStringContainsString('base_price', $content);
        $this->assertStringContainsString('99.99', $content);
    }

    /**
     * Helper: create a temporary CSV file from rows.
     *
     * @param  list<list<string>>  $rows
     */
    private function createCsvFile(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_test_');
        if ($path === false) {
            $this->fail('Could not create temp file for CSV test.');
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->fail('Could not open temp file for writing.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
