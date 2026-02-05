<?php

namespace App\Services\Finance;

use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PricingService - Module S Integration Placeholder
 *
 * This service provides pricing resolution for invoices. It integrates with
 * Module S (Commercial & Sales Management) to fetch:
 * - Active prices from Price Books
 * - Applied discounts from Offers
 * - Tax rates based on customer geography and product type
 *
 * Note: This is a placeholder implementation. When Module S is implemented,
 * this service should delegate to PriceBookService and OfferService for
 * actual price resolution.
 *
 * Pricing flow:
 * 1. Check Allocation (Module A): Is SKU available in this market/channel?
 * 2. Get Active Offer (Module S): Is there an active Offer for this SKU/channel/customer?
 * 3. Resolve Price Book (Module S): Which Price Book applies?
 * 4. Get Base Price (Module S): PriceBookEntry.base_price
 * 5. Apply Benefit (Module S): If Offer has discount/fixed price, apply it
 * 6. Calculate Final Price: unit_price = final resolved price
 * 7. Determine Tax: Based on customer geography + product type
 */
class PricingService
{
    /**
     * Standard VAT rates by country code.
     * These are placeholder rates - production should use a tax rate service.
     *
     * @var array<string, string>
     */
    protected const STANDARD_VAT_RATES = [
        'IT' => '22.00',  // Italy
        'FR' => '20.00',  // France
        'DE' => '19.00',  // Germany
        'ES' => '21.00',  // Spain
        'GB' => '20.00',  // United Kingdom
        'US' => '0.00',   // United States (sales tax varies by state, handled separately)
        'CH' => '8.10',   // Switzerland (reduced rate for wine)
        'AT' => '20.00',  // Austria
        'BE' => '21.00',  // Belgium
        'NL' => '21.00',  // Netherlands
        'PT' => '23.00',  // Portugal
        'HK' => '0.00',   // Hong Kong (no VAT)
        'SG' => '9.00',   // Singapore (GST)
    ];

    /**
     * Default VAT rate for unknown countries.
     */
    protected const DEFAULT_VAT_RATE = '22.00';

    /**
     * Resolve pricing for a sellable SKU.
     *
     * This method returns the current active price for a SKU based on:
     * - Customer tier/segment
     * - Active offers and promotions
     * - Price book configuration
     *
     * @return array{
     *     unit_price: string,
     *     currency: string,
     *     price_book_id: string|null,
     *     price_book_entry_id: string|null,
     *     offer_id: string|null,
     *     discount_applied: string|null,
     *     pricing_snapshot_id: string
     * }
     */
    public function resolvePrice(
        SellableSku $sellableSku,
        Customer $customer,
        string $channel = 'direct',
        string $currency = 'EUR'
    ): array {
        // Generate a unique pricing snapshot ID for audit trail
        $pricingSnapshotId = $this->generatePricingSnapshotId();

        // TODO: When Module S is implemented, integrate with:
        // - PriceBookService::getActiveForContext($sellableSku, $customer, $channel)
        // - OfferService::resolvePrice($sellableSku, $customer, $priceBook)

        // Placeholder: Use SKU base price if available, otherwise default
        $basePrice = $this->getSkuBasePrice($sellableSku, $currency);

        Log::channel('finance')->debug('Resolved pricing for SKU', [
            'sellable_sku_id' => $sellableSku->id,
            'sku_code' => $sellableSku->sku_code ?? 'unknown',
            'customer_id' => $customer->id,
            'channel' => $channel,
            'currency' => $currency,
            'unit_price' => $basePrice,
            'pricing_snapshot_id' => $pricingSnapshotId,
        ]);

        return [
            'unit_price' => $basePrice,
            'currency' => $currency,
            'price_book_id' => null, // TODO: From Module S
            'price_book_entry_id' => null, // TODO: From Module S
            'offer_id' => null, // TODO: From Module S
            'discount_applied' => null, // TODO: From Module S
            'pricing_snapshot_id' => $pricingSnapshotId,
        ];
    }

    /**
     * Resolve tax rate based on customer geography and product type.
     *
     * Tax determination follows these rules:
     * - Use customer billing country for tax jurisdiction
     * - Apply product-specific tax rates (wine may have reduced rates)
     * - Handle B2B reverse charge where applicable
     *
     * @return array{
     *     tax_rate: string,
     *     tax_jurisdiction: string,
     *     tax_type: string,
     *     reverse_charge: bool
     * }
     */
    public function resolveTaxRate(
        SellableSku $sellableSku,
        Customer $customer,
        ?string $shippingCountry = null
    ): array {
        // Determine tax jurisdiction (shipping country takes precedence if provided)
        $taxCountry = $shippingCountry ?? $this->getCustomerTaxCountry($customer);
        $taxRate = $this->getTaxRateForCountry($taxCountry);

        // Determine tax type based on product
        $taxType = $this->determineTaxType($sellableSku);

        // Check for reverse charge (B2B within EU)
        $reverseCharge = $this->shouldApplyReverseCharge($customer, $taxCountry);
        if ($reverseCharge) {
            $taxRate = '0.00';
        }

        Log::channel('finance')->debug('Resolved tax rate for sale', [
            'sellable_sku_id' => $sellableSku->id,
            'customer_id' => $customer->id,
            'tax_country' => $taxCountry,
            'tax_rate' => $taxRate,
            'tax_type' => $taxType,
            'reverse_charge' => $reverseCharge,
        ]);

        return [
            'tax_rate' => $taxRate,
            'tax_jurisdiction' => $taxCountry,
            'tax_type' => $taxType,
            'reverse_charge' => $reverseCharge,
        ];
    }

    /**
     * Get complete pricing for a sale item including price and tax.
     *
     * This is the main method for Module A to call when preparing a sale.
     * It combines pricing resolution with tax calculation and returns
     * all data needed for invoice generation.
     *
     * @return array{
     *     unit_price: string,
     *     tax_rate: string,
     *     currency: string,
     *     pricing_snapshot_id: string,
     *     pricing_metadata: array<string, mixed>
     * }
     */
    public function getPricingForSale(
        SellableSku $sellableSku,
        Customer $customer,
        string $channel = 'direct',
        string $currency = 'EUR',
        ?string $shippingCountry = null
    ): array {
        $pricing = $this->resolvePrice($sellableSku, $customer, $channel, $currency);
        $tax = $this->resolveTaxRate($sellableSku, $customer, $shippingCountry);

        return [
            'unit_price' => $pricing['unit_price'],
            'tax_rate' => $tax['tax_rate'],
            'currency' => $pricing['currency'],
            'pricing_snapshot_id' => $pricing['pricing_snapshot_id'],
            'pricing_metadata' => [
                'price_book_id' => $pricing['price_book_id'],
                'price_book_entry_id' => $pricing['price_book_entry_id'],
                'offer_id' => $pricing['offer_id'],
                'discount_applied' => $pricing['discount_applied'],
                'tax_jurisdiction' => $tax['tax_jurisdiction'],
                'tax_type' => $tax['tax_type'],
                'reverse_charge' => $tax['reverse_charge'],
                'resolved_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Validate that provided pricing matches expected pricing.
     *
     * This method can be used to verify that pricing provided by Module A
     * matches what Module S would calculate, for audit purposes.
     */
    public function validatePricing(
        SellableSku $sellableSku,
        Customer $customer,
        string $providedPrice,
        string $providedTaxRate,
        string $channel = 'direct',
        string $currency = 'EUR'
    ): bool {
        $expected = $this->getPricingForSale($sellableSku, $customer, $channel, $currency);

        $priceMatch = bccomp($providedPrice, $expected['unit_price'], 2) === 0;
        $taxMatch = bccomp($providedTaxRate, $expected['tax_rate'], 2) === 0;

        if (! $priceMatch || ! $taxMatch) {
            Log::channel('finance')->warning('Pricing validation mismatch', [
                'sellable_sku_id' => $sellableSku->id,
                'customer_id' => $customer->id,
                'provided_price' => $providedPrice,
                'expected_price' => $expected['unit_price'],
                'provided_tax_rate' => $providedTaxRate,
                'expected_tax_rate' => $expected['tax_rate'],
            ]);
        }

        return $priceMatch && $taxMatch;
    }

    /**
     * Generate a unique pricing snapshot ID for audit trail.
     */
    protected function generatePricingSnapshotId(): string
    {
        return 'PRC-'.now()->format('Ymd-His').'-'.Str::random(8);
    }

    /**
     * Get the base price for a SKU.
     *
     * TODO: When Module S is implemented, this should fetch from PriceBookEntry.
     */
    protected function getSkuBasePrice(SellableSku $sellableSku, string $currency): string
    {
        // Placeholder: Check if SKU has a price attribute (for future Module S integration)
        // For now, return a placeholder price indicating Module S integration is needed
        $defaultPrice = '0.00';

        Log::channel('finance')->debug('Using placeholder pricing - Module S integration pending', [
            'sellable_sku_id' => $sellableSku->id,
            'currency' => $currency,
            'note' => 'Actual pricing should come from Module S Price Books',
        ]);

        return $defaultPrice;
    }

    /**
     * Get the customer's tax country (billing country).
     */
    protected function getCustomerTaxCountry(Customer $customer): string
    {
        // TODO: When Module K (Parties, Customers & Eligibility) is fully implemented,
        // use customer's billing address country
        // For now, default to Italy as base jurisdiction

        // Check if customer has a country attribute
        /** @var mixed $country */
        $country = $customer->country ?? null;
        if ($country !== null && is_string($country)) {
            return $country;
        }

        // Check metadata for country
        /** @var mixed $metadata */
        $metadata = $customer->metadata ?? null;
        if (is_array($metadata) && isset($metadata['country']) && is_string($metadata['country'])) {
            return $metadata['country'];
        }

        // Default to Italy (business base)
        return 'IT';
    }

    /**
     * Get the VAT rate for a country.
     */
    protected function getTaxRateForCountry(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        return self::STANDARD_VAT_RATES[$countryCode] ?? self::DEFAULT_VAT_RATE;
    }

    /**
     * Determine the tax type based on product characteristics.
     */
    protected function determineTaxType(SellableSku $sellableSku): string
    {
        // TODO: When Module 0 (PIM) product attributes are available,
        // check for specific product tax categories (wine, spirits, etc.)

        // Default to standard rate for wine
        return 'standard_rate';
    }

    /**
     * Check if reverse charge VAT should apply.
     *
     * Reverse charge applies for B2B sales within EU where:
     * - Seller is in one EU country
     * - Buyer is in another EU country
     * - Buyer has a valid VAT number
     */
    protected function shouldApplyReverseCharge(Customer $customer, string $taxCountry): bool
    {
        // TODO: Implement when Module K has VAT number validation
        // - Check if customer is B2B (has valid VAT number)
        // - Check if tax country is different from seller country
        // - Both must be within EU

        return false;
    }

    /**
     * Get all supported tax jurisdictions.
     *
     * @return array<string, string>
     */
    public function getSupportedTaxJurisdictions(): array
    {
        return self::STANDARD_VAT_RATES;
    }
}
