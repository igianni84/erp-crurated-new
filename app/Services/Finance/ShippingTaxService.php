<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Log;

/**
 * ShippingTaxService - VAT/Duty handling for shipping invoices (INV2)
 *
 * This service provides tax rate determination and duty calculation
 * for shipping invoices based on destination country.
 *
 * Key responsibilities:
 * - Determine VAT rate based on shipping destination country
 * - Calculate customs duties for cross-border shipments
 * - Provide tax breakdown information for invoice display
 * - Handle EU/non-EU destination tax differences
 *
 * Tax determination rules:
 * - For domestic shipments (origin = destination): Apply origin country VAT
 * - For EU intra-community shipments: VAT depends on B2B/B2C status
 * - For non-EU shipments: Zero-rated export, but duties may apply
 */
class ShippingTaxService
{
    /**
     * Standard VAT rates by country code for shipping services.
     * These rates apply to shipping and handling fees.
     *
     * @var array<string, string>
     */
    protected const VAT_RATES = [
        // EU Countries
        'IT' => '22.00',  // Italy
        'FR' => '20.00',  // France
        'DE' => '19.00',  // Germany
        'ES' => '21.00',  // Spain
        'AT' => '20.00',  // Austria
        'BE' => '21.00',  // Belgium
        'NL' => '21.00',  // Netherlands
        'PT' => '23.00',  // Portugal
        'GR' => '24.00',  // Greece
        'IE' => '23.00',  // Ireland
        'PL' => '23.00',  // Poland
        'CZ' => '21.00',  // Czech Republic
        'SK' => '20.00',  // Slovakia
        'HU' => '27.00',  // Hungary
        'RO' => '19.00',  // Romania
        'BG' => '20.00',  // Bulgaria
        'HR' => '25.00',  // Croatia
        'SI' => '22.00',  // Slovenia
        'LT' => '21.00',  // Lithuania
        'LV' => '21.00',  // Latvia
        'EE' => '22.00',  // Estonia
        'FI' => '24.00',  // Finland
        'SE' => '25.00',  // Sweden
        'DK' => '25.00',  // Denmark
        'LU' => '17.00',  // Luxembourg
        'MT' => '18.00',  // Malta
        'CY' => '19.00',  // Cyprus

        // Non-EU Countries (usually zero-rated exports)
        'GB' => '20.00',  // United Kingdom (post-Brexit)
        'CH' => '8.10',   // Switzerland
        'NO' => '25.00',  // Norway
        'US' => '0.00',   // United States (no federal VAT)
        'CA' => '5.00',   // Canada (GST)
        'AU' => '10.00',  // Australia (GST)
        'JP' => '10.00',  // Japan (consumption tax)
        'CN' => '13.00',  // China (VAT)
        'HK' => '0.00',   // Hong Kong (no VAT)
        'SG' => '9.00',   // Singapore (GST)
    ];

    /**
     * EU member state country codes.
     *
     * @var array<int, string>
     */
    protected const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Default VAT rate for unknown countries.
     */
    protected const DEFAULT_VAT_RATE = '22.00';

    /**
     * Seller/Business country (for tax nexus determination).
     */
    protected string $sellerCountry;

    public function __construct()
    {
        $this->sellerCountry = config('finance.pricing.seller_country', 'IT');
    }

    /**
     * Determine the tax rate for a shipping invoice based on destination country.
     *
     * This is the main method for determining VAT/tax rate on shipping costs.
     *
     * @return array{
     *     tax_rate: string,
     *     tax_jurisdiction: string,
     *     tax_type: string,
     *     is_export: bool,
     *     is_eu_intra_community: bool,
     *     zero_rated_reason: string|null
     * }
     */
    public function determineTaxRate(
        string $destinationCountry,
        ?string $originCountry = null,
        bool $isB2B = false,
        ?string $customerVatNumber = null
    ): array {
        $originCountry = $originCountry ?? $this->sellerCountry;
        $destinationCountry = strtoupper($destinationCountry);
        $originCountry = strtoupper($originCountry);

        // Domestic shipment (origin = destination)
        if ($originCountry === $destinationCountry) {
            return $this->getDomesticTaxRate($destinationCountry);
        }

        // Check if this is an EU intra-community transaction
        $originInEu = $this->isEuCountry($originCountry);
        $destinationInEu = $this->isEuCountry($destinationCountry);

        // EU to EU (intra-community)
        if ($originInEu && $destinationInEu) {
            return $this->getEuIntraCommunityTaxRate($destinationCountry, $isB2B, $customerVatNumber);
        }

        // Export from EU to outside EU
        if ($originInEu) {
            // If we reach here, destination is not in EU (export)
            return $this->getExportTaxRate($destinationCountry);
        }

        // Import to EU (or non-EU to non-EU)
        return $this->getImportTaxRate($destinationCountry, $originCountry);
    }

    /**
     * Calculate duties for cross-border shipments.
     *
     * This method estimates customs duties based on shipment value and destination.
     *
     * @param  array<string, mixed>|null  $productCategories  Optional product categories for specific duty rates
     * @return array{
     *     duties_applicable: bool,
     *     estimated_duty_rate: string,
     *     estimated_duty_amount: string,
     *     duty_type: string,
     *     de_minimis_applies: bool,
     *     de_minimis_threshold: string|null,
     *     notes: string
     * }
     */
    public function calculateDuties(
        string $originCountry,
        string $destinationCountry,
        string $shipmentValue,
        string $currency = 'EUR',
        ?array $productCategories = null
    ): array {
        $originCountry = strtoupper($originCountry);
        $destinationCountry = strtoupper($destinationCountry);

        // Domestic - no duties
        if ($originCountry === $destinationCountry) {
            return $this->noDutiesResult('Domestic shipment - no customs duties');
        }

        // EU intra-community - no duties
        if ($this->isEuCountry($originCountry) && $this->isEuCountry($destinationCountry)) {
            return $this->noDutiesResult('EU intra-community shipment - no customs duties');
        }

        // Check de minimis threshold
        $deMinimisThreshold = $this->getDeMinimisThreshold($destinationCountry, $currency);
        if ($deMinimisThreshold !== null && bccomp($shipmentValue, $deMinimisThreshold, 2) <= 0) {
            return [
                'duties_applicable' => false,
                'estimated_duty_rate' => '0.00',
                'estimated_duty_amount' => '0.00',
                'duty_type' => 'de_minimis_exempt',
                'de_minimis_applies' => true,
                'de_minimis_threshold' => $deMinimisThreshold,
                'notes' => "Shipment value below de minimis threshold ({$currency} {$deMinimisThreshold})",
            ];
        }

        // Calculate estimated duties
        $dutyRate = $this->getEstimatedDutyRate($destinationCountry, $productCategories);
        $dutyAmount = bcmul($shipmentValue, bcdiv($dutyRate, '100', 6), 2);

        Log::channel('finance')->debug('Calculated customs duties', [
            'origin_country' => $originCountry,
            'destination_country' => $destinationCountry,
            'shipment_value' => $shipmentValue,
            'currency' => $currency,
            'duty_rate' => $dutyRate,
            'duty_amount' => $dutyAmount,
        ]);

        return [
            'duties_applicable' => true,
            'estimated_duty_rate' => $dutyRate,
            'estimated_duty_amount' => $dutyAmount,
            'duty_type' => 'customs_duty',
            'de_minimis_applies' => false,
            'de_minimis_threshold' => $deMinimisThreshold,
            'notes' => $this->getDutyNotes($destinationCountry),
        ];
    }

    /**
     * Get a complete tax breakdown for display in invoice detail.
     *
     * @param  array<int, array{tax_rate: string, tax_amount: string, unit_price: string, quantity: string, metadata?: array<string, mixed>}>  $invoiceLines
     * @return array{
     *     total_subtotal: string,
     *     total_tax: string,
     *     tax_breakdown: array<string, array{rate: string, taxable_amount: string, tax_amount: string, line_count: int, description: string}>,
     *     has_mixed_rates: bool,
     *     destination_country: string|null,
     *     is_cross_border: bool,
     *     duty_summary: array{has_duties: bool, duty_amount: string}|null
     * }
     */
    public function getTaxBreakdown(array $invoiceLines, ?string $destinationCountry = null, ?string $originCountry = null): array
    {
        $taxBreakdown = [];
        $totalSubtotal = '0.00';
        $totalTax = '0.00';
        $dutyAmount = '0.00';
        $hasDuties = false;

        foreach ($invoiceLines as $line) {
            $lineSubtotal = bcmul($line['quantity'], $line['unit_price'], 2);
            $lineTax = $line['tax_amount'];
            $taxRate = $line['tax_rate'];

            $totalSubtotal = bcadd($totalSubtotal, $lineSubtotal, 2);
            $totalTax = bcadd($totalTax, $lineTax, 2);

            // Check for duty lines
            $lineType = $line['metadata']['line_type'] ?? null;
            if ($lineType === 'duties') {
                $hasDuties = true;
                $dutyAmount = bcadd($dutyAmount, $lineSubtotal, 2);
            }

            // Group by tax rate
            $rateKey = $taxRate;
            if (! isset($taxBreakdown[$rateKey])) {
                $taxBreakdown[$rateKey] = [
                    'rate' => $taxRate,
                    'taxable_amount' => '0.00',
                    'tax_amount' => '0.00',
                    'line_count' => 0,
                    'description' => $this->getTaxRateDescription($taxRate),
                ];
            }

            $taxBreakdown[$rateKey]['taxable_amount'] = bcadd($taxBreakdown[$rateKey]['taxable_amount'], $lineSubtotal, 2);
            $taxBreakdown[$rateKey]['tax_amount'] = bcadd($taxBreakdown[$rateKey]['tax_amount'], $lineTax, 2);
            $taxBreakdown[$rateKey]['line_count']++;
        }

        // Sort by tax rate (highest first)
        uasort($taxBreakdown, fn (array $a, array $b): int => bccomp($b['rate'], $a['rate'], 2));

        $isCrossBorder = $originCountry !== null && $destinationCountry !== null && $originCountry !== $destinationCountry;

        return [
            'total_subtotal' => $totalSubtotal,
            'total_tax' => $totalTax,
            'tax_breakdown' => $taxBreakdown,
            'has_mixed_rates' => count($taxBreakdown) > 1,
            'destination_country' => $destinationCountry,
            'is_cross_border' => $isCrossBorder,
            'duty_summary' => $hasDuties ? ['has_duties' => true, 'duty_amount' => $dutyAmount] : null,
        ];
    }

    /**
     * Get the VAT rate for a specific country.
     */
    public function getVatRateForCountry(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        return self::VAT_RATES[$countryCode] ?? self::DEFAULT_VAT_RATE;
    }

    /**
     * Check if a country is an EU member state.
     */
    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EU_COUNTRIES, true);
    }

    /**
     * Get all supported tax jurisdictions with their rates.
     *
     * @return array<string, array{rate: string, name: string, is_eu: bool}>
     */
    public function getSupportedJurisdictions(): array
    {
        $jurisdictions = [];

        foreach (self::VAT_RATES as $code => $rate) {
            $jurisdictions[$code] = [
                'rate' => $rate,
                'name' => $this->getCountryName($code),
                'is_eu' => $this->isEuCountry($code),
            ];
        }

        return $jurisdictions;
    }

    /**
     * Get tax rate for domestic shipment.
     *
     * @return array{tax_rate: string, tax_jurisdiction: string, tax_type: string, is_export: bool, is_eu_intra_community: bool, zero_rated_reason: string|null}
     */
    protected function getDomesticTaxRate(string $country): array
    {
        return [
            'tax_rate' => $this->getVatRateForCountry($country),
            'tax_jurisdiction' => $country,
            'tax_type' => 'standard_vat',
            'is_export' => false,
            'is_eu_intra_community' => false,
            'zero_rated_reason' => null,
        ];
    }

    /**
     * Get tax rate for EU intra-community transaction.
     *
     * @return array{tax_rate: string, tax_jurisdiction: string, tax_type: string, is_export: bool, is_eu_intra_community: bool, zero_rated_reason: string|null}
     */
    protected function getEuIntraCommunityTaxRate(string $destinationCountry, bool $isB2B, ?string $customerVatNumber): array
    {
        // B2B with valid VAT number = reverse charge (zero-rated)
        if ($isB2B && $customerVatNumber !== null) {
            return [
                'tax_rate' => '0.00',
                'tax_jurisdiction' => $destinationCountry,
                'tax_type' => 'reverse_charge',
                'is_export' => false,
                'is_eu_intra_community' => true,
                'zero_rated_reason' => 'EU B2B reverse charge - customer VAT: '.$customerVatNumber,
            ];
        }

        // B2C = destination country VAT rate
        return [
            'tax_rate' => $this->getVatRateForCountry($destinationCountry),
            'tax_jurisdiction' => $destinationCountry,
            'tax_type' => 'destination_vat',
            'is_export' => false,
            'is_eu_intra_community' => true,
            'zero_rated_reason' => null,
        ];
    }

    /**
     * Get tax rate for export outside EU.
     *
     * @return array{tax_rate: string, tax_jurisdiction: string, tax_type: string, is_export: bool, is_eu_intra_community: bool, zero_rated_reason: string|null}
     */
    protected function getExportTaxRate(string $destinationCountry): array
    {
        return [
            'tax_rate' => '0.00',
            'tax_jurisdiction' => $destinationCountry,
            'tax_type' => 'zero_rated_export',
            'is_export' => true,
            'is_eu_intra_community' => false,
            'zero_rated_reason' => 'Export outside EU - zero-rated',
        ];
    }

    /**
     * Get tax rate for import to destination country.
     *
     * @return array{tax_rate: string, tax_jurisdiction: string, tax_type: string, is_export: bool, is_eu_intra_community: bool, zero_rated_reason: string|null}
     */
    protected function getImportTaxRate(string $destinationCountry, string $originCountry): array
    {
        // For imports, typically the import VAT is handled by customs at destination
        // The invoice from the shipper may be zero-rated
        return [
            'tax_rate' => '0.00',
            'tax_jurisdiction' => $destinationCountry,
            'tax_type' => 'import_duty_applicable',
            'is_export' => true,
            'is_eu_intra_community' => false,
            'zero_rated_reason' => "Cross-border shipment from {$originCountry} to {$destinationCountry} - VAT collected at import",
        ];
    }

    /**
     * Return a result indicating no duties apply.
     *
     * @return array{duties_applicable: bool, estimated_duty_rate: string, estimated_duty_amount: string, duty_type: string, de_minimis_applies: bool, de_minimis_threshold: string|null, notes: string}
     */
    protected function noDutiesResult(string $reason): array
    {
        return [
            'duties_applicable' => false,
            'estimated_duty_rate' => '0.00',
            'estimated_duty_amount' => '0.00',
            'duty_type' => 'not_applicable',
            'de_minimis_applies' => false,
            'de_minimis_threshold' => null,
            'notes' => $reason,
        ];
    }

    /**
     * Get de minimis threshold for a country.
     * Below this value, customs duties typically don't apply.
     */
    protected function getDeMinimisThreshold(string $countryCode, string $currency): ?string
    {
        // De minimis thresholds vary by country
        // These are approximate values in EUR equivalents
        $thresholds = [
            'US' => '800.00',   // $800 USD ≈ €730
            'GB' => '135.00',   // £135 ≈ €155
            'CH' => '65.00',    // CHF 65 ≈ €68
            'AU' => '1000.00',  // AUD 1000 ≈ €600
            'CA' => '20.00',    // CAD 20 ≈ €14
            'JP' => '10000.00', // ¥10,000 ≈ €65
            'SG' => '400.00',   // SGD 400 ≈ €275
            'HK' => '0.00',     // No de minimis
        ];

        // Default for EU imports
        if (! isset($thresholds[$countryCode]) && ! $this->isEuCountry($countryCode)) {
            return '150.00'; // EU standard for non-commercial imports
        }

        return $thresholds[$countryCode] ?? null;
    }

    /**
     * Get estimated duty rate for a destination country.
     *
     * @param  array<string, mixed>|null  $productCategories
     */
    protected function getEstimatedDutyRate(string $countryCode, ?array $productCategories = null): string
    {
        // Wine/alcohol typically has higher duty rates
        // This is a simplified estimate - real rates depend on HS codes
        $baseRates = [
            'US' => '3.50',   // Average for wine
            'GB' => '0.00',   // Duty suspended for wine under trade agreements
            'CH' => '0.00',   // Wine included in trade agreements
            'AU' => '5.00',   // Wine Equalisation Tax separate
            'CA' => '0.00',   // Wine duty depends on province
            'JP' => '15.00',  // Higher for wine
            'CN' => '14.00',  // Wine duty rate
            'SG' => '0.00',   // No customs duty for wine
            'HK' => '0.00',   // Zero duty on wine since 2008
        ];

        return $baseRates[$countryCode] ?? '5.00'; // Default estimate
    }

    /**
     * Get notes about duties for a destination country.
     */
    protected function getDutyNotes(string $countryCode): string
    {
        $notes = [
            'US' => 'US customs duties may apply. Consult customs broker for exact rates.',
            'GB' => 'UK post-Brexit customs may apply. Check UKGT rates.',
            'CH' => 'Swiss customs duties may apply. VAT collected at import.',
            'AU' => 'Australian customs duties and Wine Equalisation Tax may apply.',
            'JP' => 'Japanese customs duties and consumption tax apply to wine imports.',
            'CN' => 'Chinese customs duties and VAT apply. Import license may be required.',
        ];

        return $notes[$countryCode] ?? 'Customs duties may apply. Please consult local customs regulations.';
    }

    /**
     * Get description for a tax rate.
     */
    protected function getTaxRateDescription(string $taxRate): string
    {
        if (bccomp($taxRate, '0', 2) === 0) {
            return 'Zero-rated (0%)';
        }

        return "VAT at {$taxRate}%";
    }

    /**
     * Get country name for a country code.
     */
    protected function getCountryName(string $countryCode): string
    {
        $names = [
            'IT' => 'Italy',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'NL' => 'Netherlands',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'IE' => 'Ireland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HR' => 'Croatia',
            'SI' => 'Slovenia',
            'SK' => 'Slovakia',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'FI' => 'Finland',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'LU' => 'Luxembourg',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'NO' => 'Norway',
            'AU' => 'Australia',
            'CA' => 'Canada',
            'JP' => 'Japan',
            'CN' => 'China',
            'HK' => 'Hong Kong',
            'SG' => 'Singapore',
        ];

        return $names[$countryCode] ?? $countryCode;
    }
}
