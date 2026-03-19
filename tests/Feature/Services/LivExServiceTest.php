<?php

namespace Tests\Feature\Services;

use App\Services\LivExService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LivExServiceTest extends TestCase
{
    // =========================================================================
    // isConfigured
    // =========================================================================

    public function test_is_configured_returns_false_when_no_api_key(): void
    {
        config(['services.livex.api_key' => null]);

        $service = new LivExService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        config(['services.livex.api_key' => 'test-api-key']);

        $service = new LivExService;

        $this->assertTrue($service->isConfigured());
    }

    // =========================================================================
    // Mock fallback (unconfigured)
    // =========================================================================

    public function test_search_returns_mock_results_when_not_configured(): void
    {
        config(['services.livex.api_key' => null]);

        $service = new LivExService;
        $results = $service->search('Sassicaia');

        $this->assertNotEmpty($results);
        $this->assertSame('Sassicaia', $results[0]['name']);
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $service = new LivExService;

        $this->assertSame([], $service->search(''));
        $this->assertSame([], $service->search('  '));
    }

    public function test_get_by_lwin_returns_mock_when_not_configured(): void
    {
        config(['services.livex.api_key' => null]);

        $service = new LivExService;
        $result = $service->getByLwin('LWIN1100001');

        $this->assertNotNull($result);
        $this->assertSame('LWIN1100001', $result['lwin']);
        $this->assertSame('Sassicaia', $result['name']);
    }

    public function test_get_by_lwin_returns_null_for_unknown_when_not_configured(): void
    {
        config(['services.livex.api_key' => null]);

        $service = new LivExService;

        $this->assertNull($service->getByLwin('LWIN9999999'));
    }

    // =========================================================================
    // Real API calls (configured)
    // =========================================================================

    public function test_search_calls_api_when_configured(): void
    {
        config([
            'services.livex.api_key' => 'test-key',
            'services.livex.api_url' => 'https://api.liv-ex.com',
        ]);

        Http::fake([
            'api.liv-ex.com/v1/search*' => Http::response([
                'results' => [
                    [
                        'lwin' => 'LWIN9900001',
                        'name' => 'Test Wine',
                        'producer' => 'Test Producer',
                        'vintage' => 2020,
                        'appellation' => 'Test AOC',
                        'country' => 'France',
                        'region' => 'Bordeaux',
                        'classification' => null,
                        'alcohol' => 13.5,
                        'drinking_window_start' => null,
                        'drinking_window_end' => null,
                        'description' => null,
                        'image_url' => null,
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new LivExService;
        $results = $service->search('Test Wine');

        $this->assertCount(1, $results);
        $this->assertSame('LWIN9900001', $results[0]['lwin']);
        $this->assertSame('Test Wine', $results[0]['name']);

        Http::assertSentCount(1);
    }

    public function test_search_caches_results(): void
    {
        config([
            'services.livex.api_key' => 'test-key',
            'services.livex.api_url' => 'https://api.liv-ex.com',
        ]);

        Http::fake([
            'api.liv-ex.com/v1/search*' => Http::response([
                'results' => [
                    [
                        'lwin' => 'LWIN9900001',
                        'name' => 'Cached Wine',
                        'producer' => 'Test Producer',
                        'vintage' => 2020,
                        'appellation' => 'Test AOC',
                        'country' => 'France',
                        'region' => 'Bordeaux',
                    ],
                ],
            ]),
        ]);

        Cache::flush();

        $service = new LivExService;
        $service->search('Cached Wine');
        $service->search('Cached Wine'); // Should be cached

        Http::assertSentCount(1);
    }

    public function test_get_by_lwin_calls_api_when_configured(): void
    {
        config([
            'services.livex.api_key' => 'test-key',
            'services.livex.api_url' => 'https://api.liv-ex.com',
        ]);

        Http::fake([
            'api.liv-ex.com/v1/wines/LWIN9900001' => Http::response([
                'lwin' => 'LWIN9900001',
                'name' => 'Detail Wine',
                'producer' => 'Detail Producer',
                'vintage' => 2021,
                'appellation' => 'Pauillac',
                'country' => 'France',
                'region' => 'Bordeaux',
            ]),
        ]);

        Cache::flush();

        $service = new LivExService;
        $result = $service->getByLwin('LWIN9900001');

        $this->assertNotNull($result);
        $this->assertSame('LWIN9900001', $result['lwin']);
        $this->assertSame('Detail Wine', $result['name']);
    }

    public function test_search_returns_empty_on_api_failure(): void
    {
        config([
            'services.livex.api_key' => 'test-key',
            'services.livex.api_url' => 'https://api.liv-ex.com',
        ]);

        Http::fake([
            'api.liv-ex.com/v1/search*' => Http::response('Server Error', 500),
        ]);

        Cache::flush();

        $service = new LivExService;
        $results = $service->search('anything');

        $this->assertSame([], $results);
    }

    public function test_get_by_lwin_returns_null_on_api_failure(): void
    {
        config([
            'services.livex.api_key' => 'test-key',
            'services.livex.api_url' => 'https://api.liv-ex.com',
        ]);

        Http::fake([
            'api.liv-ex.com/v1/wines/*' => Http::response('Not Found', 404),
        ]);

        Cache::flush();

        $service = new LivExService;
        $result = $service->getByLwin('LWIN0000000');

        $this->assertNull($result);
    }

    // =========================================================================
    // Locked Fields
    // =========================================================================

    public function test_get_locked_fields_returns_expected_list(): void
    {
        $service = new LivExService;
        $fields = $service->getLockedFields();

        $this->assertContains('name', $fields);
        $this->assertContains('producer', $fields);
        $this->assertContains('lwin_code', $fields);
        $this->assertCount(7, $fields);
    }

    public function test_locked_fields_constant_matches_service_method(): void
    {
        $service = new LivExService;

        $this->assertSame(LivExService::LOCKED_FIELDS, $service->getLockedFields());
    }
}
