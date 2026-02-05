<?php

namespace App\Enums\Finance;

/**
 * Enum ServiceFeeType
 *
 * Types of service fees that can be charged on INV4 (Service Events) invoices.
 *
 * These fee types are used to categorize invoice lines for reporting,
 * filtering, and display purposes. They help distinguish between:
 * - Event attendance fees (entry, tickets)
 * - Tasting service fees (wine tasting sessions)
 * - Consultation fees (expert advice, cellar management)
 * - Other miscellaneous service fees
 *
 * IMPORTANT: INV4 lines should NOT include inventory costs (bottles).
 * Only service-related fees are allowed on INV4 invoices.
 */
enum ServiceFeeType: string
{
    case EventAttendance = 'event_attendance';
    case TastingFee = 'tasting_fee';
    case Consultation = 'consultation';
    case OtherService = 'other_service';

    /**
     * Get the human-readable label for this service fee type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EventAttendance => 'Event Attendance',
            self::TastingFee => 'Tasting Fee',
            self::Consultation => 'Consultation',
            self::OtherService => 'Other Service',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::EventAttendance => 'primary',
            self::TastingFee => 'success',
            self::Consultation => 'info',
            self::OtherService => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::EventAttendance => 'heroicon-o-ticket',
            self::TastingFee => 'heroicon-o-beaker',
            self::Consultation => 'heroicon-o-chat-bubble-left-right',
            self::OtherService => 'heroicon-o-wrench-screwdriver',
        };
    }

    /**
     * Get a description for this service fee type.
     */
    public function description(): string
    {
        return match ($this) {
            self::EventAttendance => 'Entry fees, tickets, and attendance charges for events',
            self::TastingFee => 'Wine tasting session fees and related services',
            self::Consultation => 'Expert consultation, cellar management, and advisory services',
            self::OtherService => 'Other miscellaneous service fees',
        };
    }

    /**
     * Get all service fee types that are event-related.
     *
     * @return array<self>
     */
    public static function eventRelatedTypes(): array
    {
        return [
            self::EventAttendance,
            self::TastingFee,
        ];
    }

    /**
     * Get all service fee types that are advisory/consultation-related.
     *
     * @return array<self>
     */
    public static function advisoryTypes(): array
    {
        return [
            self::Consultation,
        ];
    }

    /**
     * Check if this service fee type is event-related.
     */
    public function isEventRelated(): bool
    {
        return in_array($this, self::eventRelatedTypes(), true);
    }

    /**
     * Check if this service fee type is advisory/consultation.
     */
    public function isAdvisory(): bool
    {
        return in_array($this, self::advisoryTypes(), true);
    }

    /**
     * Check if this is a tasting event type.
     */
    public function isTastingEvent(): bool
    {
        return $this === self::TastingFee;
    }

    /**
     * Check if this is a consultation type.
     */
    public function isConsultation(): bool
    {
        return $this === self::Consultation;
    }

    /**
     * Get the expected line description prefix for this service type.
     */
    public function getDescriptionPrefix(): string
    {
        return match ($this) {
            self::EventAttendance => 'Event Attendance: ',
            self::TastingFee => 'Tasting: ',
            self::Consultation => 'Consultation: ',
            self::OtherService => 'Service: ',
        };
    }

    /**
     * Create from a string value, returning null if invalid.
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Get all available types as options for forms.
     *
     * @return array<string, string>
     */
    public static function getOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
