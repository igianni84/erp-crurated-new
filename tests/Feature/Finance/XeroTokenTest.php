<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\XeroToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XeroTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_token_creates_active_record(): void
    {
        $token = XeroToken::storeToken([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'tenant_id' => 'test-tenant-id',
            'expires_in' => 1800,
        ]);

        $this->assertTrue($token->is_active);
        $this->assertSame('test-tenant-id', $token->tenant_id);
        $this->assertFalse($token->isExpired());
    }

    public function test_store_token_deactivates_previous_tokens(): void
    {
        $first = XeroToken::factory()->create();
        $this->assertTrue($first->is_active);

        XeroToken::storeToken([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 1800,
        ]);

        $first->refresh();
        $this->assertFalse($first->is_active);

        $active = XeroToken::getActive();
        $this->assertNotNull($active);
        $this->assertNotSame($first->id, $active->id);
    }

    public function test_get_active_returns_null_when_no_tokens(): void
    {
        $this->assertNull(XeroToken::getActive());
    }

    public function test_get_active_returns_active_token(): void
    {
        XeroToken::factory()->inactive()->create();
        $active = XeroToken::factory()->create();

        $found = XeroToken::getActive();
        $this->assertNotNull($found);
        $this->assertSame($active->id, $found->id);
    }

    public function test_is_expired_returns_true_for_expired_token(): void
    {
        $token = XeroToken::factory()->expired()->create();

        $this->assertTrue($token->isExpired());
    }

    public function test_is_expired_returns_false_for_valid_token(): void
    {
        $token = XeroToken::factory()->create();

        $this->assertFalse($token->isExpired());
    }

    public function test_expires_within_returns_true_for_soon_expiring(): void
    {
        $token = XeroToken::factory()->expiringSoon()->create();

        $this->assertTrue($token->expiresWithin(5));
    }

    public function test_refresh_with_updates_token_data(): void
    {
        $token = XeroToken::factory()->expired()->create();

        $token->refreshWith([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'refreshed-refresh-token',
            'expires_in' => 1800,
        ]);

        $token->refresh();
        $this->assertFalse($token->isExpired());
    }

    public function test_access_token_is_encrypted(): void
    {
        $token = XeroToken::factory()->create([
            'access_token' => 'plaintext-token-123',
        ]);

        // Re-fetch from DB and verify the raw value is not plaintext
        $raw = \Illuminate\Support\Facades\DB::table('xero_tokens')
            ->where('id', $token->id)
            ->value('access_token');

        $this->assertNotSame('plaintext-token-123', $raw);

        // But the model decrypts it
        $token->refresh();
        $this->assertSame('plaintext-token-123', $token->access_token);
    }
}
