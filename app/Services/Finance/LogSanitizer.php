<?php

namespace App\Services\Finance;

/**
 * LogSanitizer Service
 *
 * Sanitizes sensitive data from integration log payloads.
 * Used by StripeWebhook and XeroSyncLog to redact sensitive information
 * before storing payloads in the database.
 *
 * Configurable via config/finance.php 'logs.sensitive_fields'
 */
class LogSanitizer
{
    /**
     * The replacement text for redacted values.
     */
    protected const REDACTED = '[REDACTED]';

    /**
     * Cached list of sensitive field patterns.
     *
     * @var array<string>
     */
    protected array $sensitiveFields;

    public function __construct()
    {
        $this->sensitiveFields = config('finance.logs.sensitive_fields', []);
    }

    /**
     * Sanitize a payload array, redacting sensitive fields.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->sanitizeRecursively($payload);
    }

    /**
     * Recursively sanitize an array, redacting sensitive fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeRecursively(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeRecursively($value);
            } elseif (is_string($value) && $this->containsSensitivePattern($value)) {
                $sanitized[$key] = $this->redactSensitivePatterns($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a key is considered sensitive.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach ($this->sensitiveFields as $field) {
            $normalizedField = strtolower($field);

            // Exact match
            if ($normalizedKey === $normalizedField) {
                return true;
            }

            // Contains match (e.g., 'card_details' should match 'card')
            if (str_contains($normalizedKey, $normalizedField)) {
                return true;
            }

            // Underscore/camelCase variations (e.g., 'cardNumber' should match 'card_number')
            $camelField = $this->toCamelCase($normalizedField);
            if (str_contains($normalizedKey, $camelField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a snake_case string to camelCase.
     */
    protected function toCamelCase(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    /**
     * Check if a string value contains sensitive patterns.
     */
    protected function containsSensitivePattern(string $value): bool
    {
        // Card numbers (13-19 digits, possibly with spaces/dashes)
        if (preg_match('/\b(?:\d[ -]*?){13,19}\b/', $value)) {
            // Verify it's likely a card number (Luhn check is expensive, so skip)
            return true;
        }

        // IBANs (2 letter country code + 2 check digits + up to 30 alphanumeric)
        if (preg_match('/\b[A-Z]{2}\d{2}[A-Z0-9]{4,30}\b/i', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Redact sensitive patterns from a string value.
     */
    protected function redactSensitivePatterns(string $value): string
    {
        // Redact card numbers (keep last 4 digits)
        $value = preg_replace(
            '/\b(\d[ -]*?){9,15}(\d{4})\b/',
            '****-****-****-$2',
            $value
        ) ?? $value;

        // Redact IBANs (keep country code and last 4)
        $value = preg_replace(
            '/\b([A-Z]{2})\d{2}[A-Z0-9]{0,26}([A-Z0-9]{4})\b/i',
            '$1**************$2',
            $value
        ) ?? $value;

        return $value;
    }

    /**
     * Get the list of sensitive fields being checked.
     *
     * @return array<string>
     */
    public function getSensitiveFields(): array
    {
        return $this->sensitiveFields;
    }

    /**
     * Add additional sensitive fields to check.
     *
     * @param  array<string>  $fields
     */
    public function addSensitiveFields(array $fields): self
    {
        $this->sensitiveFields = array_unique(array_merge($this->sensitiveFields, $fields));

        return $this;
    }

    /**
     * Create an instance with custom sensitive fields.
     *
     * @param  array<string>  $fields
     */
    public static function withFields(array $fields): self
    {
        $instance = new self;
        $instance->sensitiveFields = $fields;

        return $instance;
    }
}
