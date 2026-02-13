<?php

namespace App\Enums\Pim;

/**
 * Enum AppellationSystem
 *
 * Defines the legal appellation/designation systems used worldwide.
 */
enum AppellationSystem: string
{
    case AOC = 'aoc';
    case AOP = 'aop';
    case DOC = 'doc';
    case DOCG = 'docg';
    case IGT = 'igt';
    case IGP = 'igp';
    case AVA = 'ava';
    case DO = 'do';
    case DOCa = 'doca';
    case VdP = 'vdp';
    case DAC = 'dac';
    case GI = 'gi';
    case DOCPt = 'doc_pt';
    case Other = 'other';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::AOC => 'AOC (Appellation d\'Origine Contrôlée)',
            self::AOP => 'AOP (Appellation d\'Origine Protégée)',
            self::DOC => 'DOC (Denominazione di Origine Controllata)',
            self::DOCG => 'DOCG (Denominazione di Origine Controllata e Garantita)',
            self::IGT => 'IGT (Indicazione Geografica Tipica)',
            self::IGP => 'IGP (Indication Géographique Protégée)',
            self::AVA => 'AVA (American Viticultural Area)',
            self::DO => 'DO (Denominación de Origen)',
            self::DOCa => 'DOCa (Denominación de Origen Calificada)',
            self::VdP => 'VDP (Verband Deutscher Prädikatsweingüter)',
            self::DAC => 'DAC (Districtus Austriae Controllatus)',
            self::GI => 'GI (Geographical Indication)',
            self::DOCPt => 'DOC (Denominação de Origem Controlada)',
            self::Other => 'Other',
        };
    }

    /**
     * Get the short label for compact display.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::AOC => 'AOC',
            self::AOP => 'AOP',
            self::DOC => 'DOC',
            self::DOCG => 'DOCG',
            self::IGT => 'IGT',
            self::IGP => 'IGP',
            self::AVA => 'AVA',
            self::DO => 'DO',
            self::DOCa => 'DOCa',
            self::VdP => 'VDP',
            self::DAC => 'DAC',
            self::GI => 'GI',
            self::DOCPt => 'DOC (PT)',
            self::Other => 'Other',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::AOC, self::AOP => 'primary',
            self::DOCG, self::DOCa => 'success',
            self::DOC, self::DOCPt => 'info',
            self::IGT, self::IGP => 'warning',
            self::AVA => 'danger',
            self::DO => 'primary',
            self::VdP => 'success',
            self::DAC => 'info',
            self::GI => 'warning',
            self::Other => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::AOC, self::AOP, self::DOC, self::DOCG, self::DOCPt => 'heroicon-o-shield-check',
            self::IGT, self::IGP => 'heroicon-o-map-pin',
            self::AVA, self::GI => 'heroicon-o-globe-americas',
            self::DO, self::DOCa => 'heroicon-o-shield-check',
            self::VdP, self::DAC => 'heroicon-o-star',
            self::Other => 'heroicon-o-tag',
        };
    }
}
