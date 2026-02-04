<?php

namespace App\Enums\Fulfillment;

/**
 * Enum Incoterms
 *
 * International Commercial Terms (Incoterms) that define the responsibilities
 * of buyers and sellers in international shipping transactions.
 *
 * Common terms used in wine industry:
 * - EXW: Ex Works (seller makes goods available at their premises)
 * - FCA: Free Carrier (seller delivers goods to carrier named by buyer)
 * - DAP: Delivered at Place (seller delivers goods to destination, uncleared)
 * - DDP: Delivered Duty Paid (seller delivers goods to destination, cleared)
 * - CIF: Cost, Insurance, and Freight (seller delivers goods on board vessel)
 * - FOB: Free on Board (seller delivers goods on board vessel at port)
 */
enum Incoterms: string
{
    case EXW = 'exw';
    case FCA = 'fca';
    case DAP = 'dap';
    case DDP = 'ddp';
    case CIF = 'cif';
    case FOB = 'fob';
    case CPT = 'cpt';
    case CIP = 'cip';

    /**
     * Get the human-readable label for this incoterm.
     */
    public function label(): string
    {
        return match ($this) {
            self::EXW => 'EXW - Ex Works',
            self::FCA => 'FCA - Free Carrier',
            self::DAP => 'DAP - Delivered at Place',
            self::DDP => 'DDP - Delivered Duty Paid',
            self::CIF => 'CIF - Cost, Insurance & Freight',
            self::FOB => 'FOB - Free on Board',
            self::CPT => 'CPT - Carriage Paid To',
            self::CIP => 'CIP - Carriage & Insurance Paid',
        };
    }

    /**
     * Get the description for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::EXW => 'Buyer takes responsibility at seller\'s premises',
            self::FCA => 'Seller delivers to carrier at named place',
            self::DAP => 'Seller delivers to destination, import duties unpaid',
            self::DDP => 'Seller delivers to destination, all duties paid',
            self::CIF => 'Seller pays cost and freight to port, insurance included',
            self::FOB => 'Seller delivers goods on board vessel at port',
            self::CPT => 'Seller pays carriage to destination',
            self::CIP => 'Seller pays carriage and insurance to destination',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::EXW => 'gray',
            self::FCA => 'info',
            self::DAP => 'warning',
            self::DDP => 'success',
            self::CIF => 'info',
            self::FOB => 'info',
            self::CPT => 'warning',
            self::CIP => 'warning',
        };
    }

    /**
     * Check if seller is responsible for import duties.
     */
    public function sellerPaysImportDuties(): bool
    {
        return $this === self::DDP;
    }

    /**
     * Check if seller is responsible for insurance.
     */
    public function sellerPaysInsurance(): bool
    {
        return in_array($this, [self::CIF, self::CIP, self::DDP], true);
    }
}
