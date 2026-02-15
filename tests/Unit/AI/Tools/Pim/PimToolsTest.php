<?php

namespace Tests\Unit\AI\Tools\Pim;

use App\AI\Tools\Pim\DataQualityIssuesTool;
use App\AI\Tools\Pim\ProductCatalogSearchTool;
use App\Enums\UserRole;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class PimToolsTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected CaseConfiguration $caseConfig;

    protected SellableSku $sellableSku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wineMaster = WineMaster::create([
            'name' => 'Brunello di Montalcino',
            'producer' => 'Biondi-Santi',
            'producer_id' => null,
            'appellation' => 'Brunello di Montalcino DOCG',
            'country' => 'Italy',
            'country_id' => null,
            'region_id' => null,
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2017,
        ]);

        $this->format = Format::create([
            'name' => 'Bottle 750ml',
            'volume_ml' => 750,
        ]);

        $this->caseConfig = CaseConfiguration::create([
            'name' => '6 bottles OWC',
            'format_id' => $this->format->id,
            'bottles_per_case' => 6,
            'case_type' => 'owc',
        ]);

        $this->sellableSku = SellableSku::create([
            'wine_variant_id' => $this->wineVariant->id,
            'format_id' => $this->format->id,
            'case_configuration_id' => $this->caseConfig->id,
            'lifecycle_status' => 'active',
            'source' => 'manual',
        ]);
    }

    // =========================================================================
    // ProductCatalogSearchTool
    // =========================================================================

    public function test_product_catalog_search_happy_path(): void
    {
        $tool = new ProductCatalogSearchTool;
        $result = $tool->handle(new Request(['query' => 'Brunello']));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertGreaterThanOrEqual(1, $data['total']);

        $found = false;
        foreach ($data['results'] as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('producer', $item);
            $this->assertArrayHasKey('type', $item);
            if ($item['name'] === 'Brunello di Montalcino') {
                $found = true;
                $this->assertEquals('Biondi-Santi', $item['producer']);
            }
        }
        $this->assertTrue($found, 'Expected to find Brunello di Montalcino in search results');
    }

    public function test_product_catalog_search_type_filter(): void
    {
        $tool = new ProductCatalogSearchTool;

        // Search by wine_master type
        $resultMaster = $tool->handle(new Request(['query' => 'Brunello', 'type' => 'wine_master']));
        $dataMaster = json_decode((string) $resultMaster, true);
        $this->assertGreaterThanOrEqual(1, $dataMaster['total']);
        foreach ($dataMaster['results'] as $item) {
            $this->assertEquals('wine_master', $item['type']);
        }

        // Search by sellable_sku type with the SKU code
        $skuCode = $this->sellableSku->sku_code;
        $resultSku = $tool->handle(new Request(['query' => $skuCode, 'type' => 'sellable_sku']));
        $dataSku = json_decode((string) $resultSku, true);
        $this->assertGreaterThanOrEqual(1, $dataSku['total']);
        foreach ($dataSku['results'] as $item) {
            $this->assertEquals('sellable_sku', $item['type']);
        }
    }

    public function test_product_catalog_search_short_query_rejected(): void
    {
        $tool = new ProductCatalogSearchTool;
        $result = $tool->handle(new Request(['query' => 'B']));
        $data = json_decode((string) $result, true);

        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('at least 2 characters', $data['message']);
    }

    public function test_product_catalog_search_authorization_viewer_granted(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new ProductCatalogSearchTool;

        // Viewer maps to Overview (10), tool requires Overview (10) => granted
        $this->assertTrue($tool->authorizeForUser($viewer));
    }

    public function test_product_catalog_search_authorization_null_role_denied(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        // Forcefully set role to null in memory to test the null guard in authorizeForUser
        $user->role = null;
        $tool = new ProductCatalogSearchTool;

        // User without role => denied
        $this->assertFalse($tool->authorizeForUser($user));
    }

    // =========================================================================
    // DataQualityIssuesTool
    // =========================================================================

    public function test_data_quality_issues_happy_path(): void
    {
        // Create a WineMaster without any variants (orphaned)
        WineMaster::create([
            'name' => 'Orphaned Wine',
            'producer' => 'Unknown Producer',
            'country' => 'France',
        ]);

        // Create a WineVariant without any SellableSkus (orphaned)
        $orphanedVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2015,
        ]);

        $tool = new DataQualityIssuesTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_issues', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertGreaterThanOrEqual(1, $data['total_issues']);

        // Each issue should have the correct structure
        foreach ($data['issues'] as $issue) {
            $this->assertArrayHasKey('type', $issue);
            $this->assertArrayHasKey('severity', $issue);
            $this->assertArrayHasKey('count', $issue);
            $this->assertArrayHasKey('sample_names', $issue);
            $this->assertContains($issue['severity'], ['high', 'medium', 'low']);
        }

        // Verify the orphaned wine master is detected
        $masterIssue = collect($data['issues'])->firstWhere('type', 'WineMaster without WineVariants');
        $this->assertNotNull($masterIssue);
        $this->assertEquals('high', $masterIssue['severity']);
        $this->assertGreaterThanOrEqual(1, $masterIssue['count']);
        $this->assertContains('Orphaned Wine', $masterIssue['sample_names']);
    }

    public function test_data_quality_issues_detects_missing_producer_and_country(): void
    {
        // The setUp wineMaster already has producer_id=null and country_id=null
        $tool = new DataQualityIssuesTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        // Check for missing producer issue
        $producerIssue = collect($data['issues'])->firstWhere('type', 'WineMaster with missing producer');
        $this->assertNotNull($producerIssue, 'Expected to find missing producer issue');
        $this->assertEquals('medium', $producerIssue['severity']);
        $this->assertContains('Brunello di Montalcino', $producerIssue['sample_names']);

        // Check for missing country issue
        $countryIssue = collect($data['issues'])->firstWhere('type', 'WineMaster with missing country');
        $this->assertNotNull($countryIssue, 'Expected to find missing country issue');
        $this->assertEquals('low', $countryIssue['severity']);

        // Check for missing region issue
        $regionIssue = collect($data['issues'])->firstWhere('type', 'WineMaster with missing region');
        $this->assertNotNull($regionIssue, 'Expected to find missing region issue');
        $this->assertEquals('low', $regionIssue['severity']);
    }

    public function test_data_quality_issues_authorization_editor_denied(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new DataQualityIssuesTool;

        // Editor maps to Basic (20), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_data_quality_issues_authorization_admin_granted(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $tool = new DataQualityIssuesTool;

        // Admin maps to Full (60), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($admin));
    }
}
