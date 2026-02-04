<?php

namespace App\Enums\Fulfillment;

/**
 * Enum Carrier
 *
 * Supported shipping carriers for fulfillment.
 * This enum provides a configurable list of carriers that can be selected
 * when creating shipping orders.
 */
enum Carrier: string
{
    case DHL = 'dhl';
    case FedEx = 'fedex';
    case UPS = 'ups';
    case TNT = 'tnt';
    case DPD = 'dpd';
    case GLS = 'gls';
    case USPS = 'usps';
    case Chronopost = 'chronopost';
    case Colissimo = 'colissimo';
    case Other = 'other';

    /**
     * Get the human-readable label for this carrier.
     */
    public function label(): string
    {
        return match ($this) {
            self::DHL => 'DHL',
            self::FedEx => 'FedEx',
            self::UPS => 'UPS',
            self::TNT => 'TNT',
            self::DPD => 'DPD',
            self::GLS => 'GLS',
            self::USPS => 'USPS',
            self::Chronopost => 'Chronopost',
            self::Colissimo => 'Colissimo',
            self::Other => 'Other',
        };
    }

    /**
     * Get the description for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::DHL => 'DHL Express - International shipping',
            self::FedEx => 'FedEx - International & domestic express',
            self::UPS => 'UPS - Worldwide shipping solutions',
            self::TNT => 'TNT - European & international delivery',
            self::DPD => 'DPD - European parcel delivery',
            self::GLS => 'GLS - European shipping network',
            self::USPS => 'USPS - United States Postal Service',
            self::Chronopost => 'Chronopost - French express delivery',
            self::Colissimo => 'Colissimo - French postal service',
            self::Other => 'Other carrier (specify in shipping method)',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::DHL => 'warning',
            self::FedEx => 'danger',
            self::UPS => 'warning',
            self::TNT => 'danger',
            self::DPD => 'danger',
            self::GLS => 'warning',
            self::USPS => 'info',
            self::Chronopost => 'info',
            self::Colissimo => 'info',
            self::Other => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return 'heroicon-o-truck';
    }

    /**
     * Get the tracking URL template for this carrier.
     * Use {tracking_number} as placeholder.
     */
    public function getTrackingUrlTemplate(): ?string
    {
        return match ($this) {
            self::DHL => 'https://www.dhl.com/en/express/tracking.html?AWB={tracking_number}',
            self::FedEx => 'https://www.fedex.com/fedextrack/?trknbr={tracking_number}',
            self::UPS => 'https://www.ups.com/track?tracknum={tracking_number}',
            self::TNT => 'https://www.tnt.com/express/en_gb/site/tracking.html?searchType=con&cons={tracking_number}',
            self::DPD => 'https://www.dpd.com/tracking?parcelNumber={tracking_number}',
            self::GLS => 'https://www.gls-group.com/EU/en/parcel-tracking?match={tracking_number}',
            self::USPS => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking_number}',
            self::Chronopost => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking_number}',
            self::Colissimo => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking_number}',
            self::Other => null,
        };
    }

    /**
     * Build the tracking URL for a specific tracking number.
     */
    public function buildTrackingUrl(string $trackingNumber): ?string
    {
        $template = $this->getTrackingUrlTemplate();

        if ($template === null) {
            return null;
        }

        return str_replace('{tracking_number}', urlencode($trackingNumber), $template);
    }
}
