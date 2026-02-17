<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoucherTradingCompleteTest extends TestCase
{
    use RefreshDatabase;

    private string $testSecret = 'test-hmac-secret-for-feature-tests';

    private string $fakeVoucherUuid;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.trading_platform.hmac_secret' => $this->testSecret]);
        $this->fakeVoucherUuid = (string) Str::uuid();
    }

    private function endpoint(): string
    {
        return "/api/vouchers/{$this->fakeVoucherUuid}/trading-complete";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{signature: string, timestamp: string, body: string}
     */
    private function signPayload(array $data, ?int $timestamp = null): array
    {
        $body = json_encode($data);
        $ts = (string) ($timestamp ?? time());
        $signature = hash_hmac('sha256', $ts.'.'.$body, $this->testSecret);

        return [
            'signature' => $signature,
            'timestamp' => $ts,
            'body' => $body,
        ];
    }

    private function makeSignedRequest(string $body, string $signature, string $timestamp): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            $this->endpoint(),
            [],
            [],
            [],
            [
                'HTTP_X-Signature' => $signature,
                'HTTP_X-Timestamp' => $timestamp,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );
    }

    public function test_trading_complete_rejects_request_without_hmac_signature(): void
    {
        $response = $this->postJson($this->endpoint(), [
            'new_customer_id' => 1,
            'trading_reference' => 'REF-001',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_trading_complete_rejects_request_with_invalid_signature(): void
    {
        $body = json_encode(['new_customer_id' => 1, 'trading_reference' => 'REF-001']);

        $response = $this->makeSignedRequest($body, 'invalid-signature', (string) time());

        $response->assertStatus(401);
    }

    public function test_trading_complete_rejects_request_with_expired_timestamp(): void
    {
        $data = ['new_customer_id' => 1, 'trading_reference' => 'REF-001'];
        $signed = $this->signPayload($data, time() - 600);

        $response = $this->makeSignedRequest($signed['body'], $signed['signature'], $signed['timestamp']);

        $response->assertStatus(401);
        $this->assertStringContainsString('expired', $response->getContent());
    }

    public function test_trading_complete_with_valid_hmac_passes_authentication(): void
    {
        $data = ['new_customer_id' => 1, 'trading_reference' => 'REF-001'];
        $signed = $this->signPayload($data);

        $response = $this->makeSignedRequest($signed['body'], $signed['signature'], $signed['timestamp']);

        // With valid HMAC on a non-existing voucher UUID, we expect 404 (model not found)
        // NOT 401 — proving the middleware passed and model binding attempted resolution
        $response->assertStatus(404);
    }

    public function test_trading_complete_is_rate_limited(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $data = ['new_customer_id' => 1, 'trading_reference' => "REF-{$i}"];
            $signed = $this->signPayload($data);

            $response = $this->makeSignedRequest($signed['body'], $signed['signature'], $signed['timestamp']);

            if ($i === 10) {
                $response->assertStatus(429);
            }
        }
    }
}
