<?php

namespace App\Models\Finance;

use Carbon\Carbon;
use Database\Factories\Finance\XeroTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Xero OAuth2 tokens (encrypted at rest).
 *
 * Uses a singleton pattern — only one active token row at a time.
 *
 * @property int $id
 * @property string $access_token
 * @property string $refresh_token
 * @property string|null $tenant_id
 * @property string $token_type
 * @property int $expires_in
 * @property Carbon $expires_at
 * @property array<string>|null $scopes
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class XeroToken extends Model
{
    /** @use HasFactory<XeroTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'access_token',
        'refresh_token',
        'tenant_id',
        'token_type',
        'expires_in',
        'expires_at',
        'scopes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'expires_in' => 'integer',
        ];
    }

    /**
     * Get the currently active token.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * Store a new token, deactivating all previous ones.
     *
     * @param  array<string, mixed>  $tokenData
     */
    public static function storeToken(array $tokenData): self
    {
        // Deactivate all existing tokens
        self::where('is_active', true)->update(['is_active' => false]);

        return self::create([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'tenant_id' => $tokenData['tenant_id'] ?? null,
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'expires_in' => $tokenData['expires_in'] ?? 1800,
            'expires_at' => Carbon::now()->addSeconds((int) ($tokenData['expires_in'] ?? 1800)),
            'scopes' => $tokenData['scopes'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the token will expire within the given minutes.
     */
    public function expiresWithin(int $minutes = 5): bool
    {
        return $this->expires_at->isBefore(now()->addMinutes($minutes));
    }

    /**
     * Update token after a refresh.
     *
     * @param  array<string, mixed>  $tokenData
     */
    public function refreshWith(array $tokenData): void
    {
        $this->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $this->refresh_token,
            'expires_in' => $tokenData['expires_in'] ?? 1800,
            'expires_at' => Carbon::now()->addSeconds((int) ($tokenData['expires_in'] ?? 1800)),
        ]);
    }
}
