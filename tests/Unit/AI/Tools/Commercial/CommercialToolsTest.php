<?php

namespace Tests\Unit\AI\Tools\Commercial;

use App\AI\Tools\Commercial\ActiveOffersTool;
use App\AI\Tools\Commercial\EmpAlertsTool;
use App\AI\Tools\Commercial\PriceBookCoverageTool;
use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Enums\Commercial\EmpConfidenceLevel;
use App\Enums\Commercial\EmpSource;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Enums\Commercial\PriceBookStatus;
use App\Enums\Commercial\PriceSource;
use App\Enums\UserRole;
use App\Models\Commercial\Channel;
use App\Models\Commercial\EstimatedMarketPrice;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\CaseConfiguration;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineMaster;
use App\Models\Pim\WineVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CommercialToolsTest extends TestCase
{
    use RefreshDatabase;

    protected WineMaster $wineMaster;

    protected WineVariant $wineVariant;

    protected Format $format;

    protected CaseConfiguration $caseConfig;

    protected SellableSku $sellableSku;

    protected Channel $channel;

    protected PriceBook $priceBook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wineMaster = WineMaster::create([
            'name' => 'Barolo Riserva',
            'producer' => 'Giacomo Conterno',
            'country' => 'Italy',
        ]);

        $this->wineVariant = WineVariant::create([
            'wine_master_id' => $this->wineMaster->id,
            'vintage_year' => 2018,
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

        $this->channel = Channel::create([
            'name' => 'B2C Europe',
            'channel_type' => ChannelType::B2c,
            'default_currency' => 'EUR',
            'allowed_commercial_models' => ['voucher_based'],
            'status' => ChannelStatus::Active,
        ]);

        $this->priceBook = PriceBook::create([
            'name' => 'EU Price Book Q1',
            'market' => 'EU',
            'currency' => 'EUR',
            'valid_from' => Carbon::now()->subMonth(),
            'status' => PriceBookStatus::Active,
        ]);
    }

    // =========================================================================
    // ActiveOffersTool
    // =========================================================================

    public function test_active_offers_happy_path(): void
    {
        // Create active offer
        Offer::create([
            'name' => 'Barolo Riserva 2018 Offer',
            'sellable_sku_id' => $this->sellableSku->id,
            'channel_id' => $this->channel->id,
            'price_book_id' => $this->priceBook->id,
            'offer_type' => OfferType::Standard,
            'visibility' => OfferVisibility::Public,
            'valid_from' => Carbon::now()->subDays(7),
            'status' => OfferStatus::Active,
        ]);

        // Create draft offer (should not appear)
        Offer::create([
            'name' => 'Draft Offer',
            'sellable_sku_id' => $this->sellableSku->id,
            'channel_id' => $this->channel->id,
            'price_book_id' => $this->priceBook->id,
            'offer_type' => OfferType::Standard,
            'visibility' => OfferVisibility::Restricted,
            'valid_from' => Carbon::now(),
            'status' => OfferStatus::Draft,
        ]);

        $tool = new ActiveOffersTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_active', $data);
        $this->assertArrayHasKey('offers', $data);
        $this->assertEquals(1, $data['total_active']);
        $this->assertCount(1, $data['offers']);

        $offer = $data['offers'][0];
        $this->assertArrayHasKey('name', $offer);
        $this->assertArrayHasKey('wine_name', $offer);
        $this->assertArrayHasKey('channel_name', $offer);
        $this->assertArrayHasKey('offer_type', $offer);
        $this->assertArrayHasKey('valid_from', $offer);
        $this->assertArrayHasKey('visibility', $offer);
        $this->assertEquals('Barolo Riserva 2018 Offer', $offer['name']);
        $this->assertEquals('Barolo Riserva', $offer['wine_name']);
        $this->assertEquals('B2C Europe', $offer['channel_name']);
        $this->assertEquals('Standard', $offer['offer_type']);
        $this->assertEquals('Public', $offer['visibility']);
    }

    public function test_active_offers_filters_by_channel(): void
    {
        $otherChannel = Channel::create([
            'name' => 'B2B Asia',
            'channel_type' => ChannelType::B2b,
            'default_currency' => 'USD',
            'allowed_commercial_models' => ['sell_through'],
            'status' => ChannelStatus::Active,
        ]);

        // Active offer on first channel
        Offer::create([
            'name' => 'EU Offer',
            'sellable_sku_id' => $this->sellableSku->id,
            'channel_id' => $this->channel->id,
            'price_book_id' => $this->priceBook->id,
            'offer_type' => OfferType::Standard,
            'visibility' => OfferVisibility::Public,
            'valid_from' => Carbon::now()->subDays(3),
            'status' => OfferStatus::Active,
        ]);

        // Active offer on second channel
        Offer::create([
            'name' => 'Asia Offer',
            'sellable_sku_id' => $this->sellableSku->id,
            'channel_id' => $otherChannel->id,
            'price_book_id' => $this->priceBook->id,
            'offer_type' => OfferType::Promotion,
            'visibility' => OfferVisibility::Restricted,
            'valid_from' => Carbon::now()->subDays(1),
            'status' => OfferStatus::Active,
        ]);

        $tool = new ActiveOffersTool;

        // Filter by first channel
        $result = $tool->handle(new Request(['channel_id' => $this->channel->id]));
        $data = json_decode((string) $result, true);

        $this->assertEquals(1, $data['total_active']);
        $this->assertCount(1, $data['offers']);
        $this->assertEquals('EU Offer', $data['offers'][0]['name']);
    }

    public function test_active_offers_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new ActiveOffersTool;

        // Viewer maps to Overview (10), tool requires Basic (20) => denied
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_active_offers_authorization_editor_granted(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new ActiveOffersTool;

        // Editor maps to Basic (20), tool requires Basic (20) => granted
        $this->assertTrue($tool->authorizeForUser($editor));
    }

    // =========================================================================
    // PriceBookCoverageTool
    // =========================================================================

    public function test_price_book_coverage_happy_path(): void
    {
        // Create a PriceBookEntry for the existing SKU
        PriceBookEntry::create([
            'price_book_id' => $this->priceBook->id,
            'sellable_sku_id' => $this->sellableSku->id,
            'base_price' => '150.00',
            'source' => PriceSource::Manual,
        ]);

        $tool = new PriceBookCoverageTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('price_books', $data);
        $this->assertNotEmpty($data['price_books']);

        $book = $data['price_books'][0];
        $this->assertArrayHasKey('name', $book);
        $this->assertArrayHasKey('status', $book);
        $this->assertArrayHasKey('total_entries', $book);
        $this->assertArrayHasKey('total_active_skus', $book);
        $this->assertArrayHasKey('coverage_percentage', $book);
        $this->assertArrayHasKey('missing_count', $book);

        $this->assertEquals('EU Price Book Q1', $book['name']);
        $this->assertEquals('Active', $book['status']);
        $this->assertEquals(1, $book['total_entries']);
        $this->assertEquals(1, $book['total_active_skus']);
        $this->assertEquals(100.0, $book['coverage_percentage']);
        $this->assertEquals(0, $book['missing_count']);
    }

    public function test_price_book_coverage_filters_by_id(): void
    {
        $otherPriceBook = PriceBook::create([
            'name' => 'UK Price Book',
            'market' => 'UK',
            'currency' => 'GBP',
            'valid_from' => Carbon::now()->subMonth(),
            'status' => PriceBookStatus::Draft,
        ]);

        PriceBookEntry::create([
            'price_book_id' => $this->priceBook->id,
            'sellable_sku_id' => $this->sellableSku->id,
            'base_price' => '150.00',
            'source' => PriceSource::Manual,
        ]);

        $tool = new PriceBookCoverageTool;

        // Filter by specific price book ID
        $result = $tool->handle(new Request(['price_book_id' => $otherPriceBook->id]));
        $data = json_decode((string) $result, true);

        $this->assertCount(1, $data['price_books']);
        $this->assertEquals('UK Price Book', $data['price_books'][0]['name']);
        $this->assertEquals(0, $data['price_books'][0]['total_entries']);
    }

    public function test_price_book_coverage_authorization_editor_denied(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $tool = new PriceBookCoverageTool;

        // Editor maps to Basic (20), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($editor));
    }

    public function test_price_book_coverage_authorization_manager_granted(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new PriceBookCoverageTool;

        // Manager maps to Standard (40), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($manager));
    }

    // =========================================================================
    // EmpAlertsTool
    // =========================================================================

    public function test_emp_alerts_happy_path(): void
    {
        // Create price book entry at 150 EUR
        PriceBookEntry::create([
            'price_book_id' => $this->priceBook->id,
            'sellable_sku_id' => $this->sellableSku->id,
            'base_price' => '150.00',
            'source' => PriceSource::Manual,
        ]);

        // Create EMP at 100 EUR => 50% above market
        EstimatedMarketPrice::create([
            'sellable_sku_id' => $this->sellableSku->id,
            'market' => 'EU',
            'emp_value' => '100.00',
            'source' => EmpSource::Livex,
            'confidence_level' => EmpConfidenceLevel::High,
            'fetched_at' => Carbon::now(),
        ]);

        $tool = new EmpAlertsTool;
        $result = $tool->handle(new Request([]));
        $data = json_decode((string) $result, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('threshold_percent', $data);
        $this->assertArrayHasKey('total_alerts', $data);
        $this->assertArrayHasKey('alerts', $data);

        // Default threshold 20%, our price is 50% above market => alert
        $this->assertEquals(20, $data['threshold_percent']);
        $this->assertEquals(1, $data['total_alerts']);

        $alert = $data['alerts'][0];
        $this->assertArrayHasKey('wine_name', $alert);
        $this->assertArrayHasKey('our_price', $alert);
        $this->assertArrayHasKey('market_price', $alert);
        $this->assertArrayHasKey('deviation_percent', $alert);
        $this->assertArrayHasKey('direction', $alert);
        $this->assertArrayHasKey('confidence_level', $alert);
        $this->assertArrayHasKey('price_book_name', $alert);

        $this->assertEquals('Barolo Riserva', $alert['wine_name']);
        $this->assertEquals('above', $alert['direction']);
        $this->assertEquals(50.0, $alert['deviation_percent']);
        $this->assertEquals('High', $alert['confidence_level']);
    }

    public function test_emp_alerts_threshold_parameter(): void
    {
        // Price at 115 EUR, EMP at 100 EUR => 15% above
        PriceBookEntry::create([
            'price_book_id' => $this->priceBook->id,
            'sellable_sku_id' => $this->sellableSku->id,
            'base_price' => '115.00',
            'source' => PriceSource::Manual,
        ]);

        EstimatedMarketPrice::create([
            'sellable_sku_id' => $this->sellableSku->id,
            'market' => 'EU',
            'emp_value' => '100.00',
            'source' => EmpSource::Internal,
            'confidence_level' => EmpConfidenceLevel::Medium,
            'fetched_at' => Carbon::now(),
        ]);

        $tool = new EmpAlertsTool;

        // With default 20% threshold => no alerts (15% < 20%)
        $resultDefault = $tool->handle(new Request([]));
        $dataDefault = json_decode((string) $resultDefault, true);
        $this->assertEquals(0, $dataDefault['total_alerts']);

        // With 10% threshold => alert triggered (15% > 10%)
        $resultLower = $tool->handle(new Request(['threshold_percent' => 10]));
        $dataLower = json_decode((string) $resultLower, true);
        $this->assertEquals(1, $dataLower['total_alerts']);
        $this->assertEquals(10.0, $dataLower['threshold_percent']);
    }

    public function test_emp_alerts_authorization_viewer_denied(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $tool = new EmpAlertsTool;

        // Viewer maps to Overview (10), tool requires Standard (40) => denied
        $this->assertFalse($tool->authorizeForUser($viewer));
    }

    public function test_emp_alerts_authorization_manager_granted(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $tool = new EmpAlertsTool;

        // Manager maps to Standard (40), tool requires Standard (40) => granted
        $this->assertTrue($tool->authorizeForUser($manager));
    }
}
