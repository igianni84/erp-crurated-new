<?php

namespace App\Enums\AI;

use App\Enums\UserRole;

/**
 * Enum ToolAccessLevel
 *
 * Access levels for AI tools, mapped from UserRole.
 * Numeric values (10/20/40/60) are intentionally different from
 * UserRole::level() values (20/40/60/80/100). The forRole() method
 * is the only bridge between the two systems.
 */
enum ToolAccessLevel: int
{
    case Overview = 10;
    case Basic = 20;
    case Standard = 40;
    case Full = 60;

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Overview',
            self::Basic => 'Basic',
            self::Standard => 'Standard',
            self::Full => 'Full',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Overview => 'gray',
            self::Basic => 'info',
            self::Standard => 'warning',
            self::Full => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Overview => 'heroicon-o-eye',
            self::Basic => 'heroicon-o-magnifying-glass',
            self::Standard => 'heroicon-o-chart-bar',
            self::Full => 'heroicon-o-shield-check',
        };
    }

    public static function forRole(UserRole $role): self
    {
        return match ($role) {
            UserRole::Viewer => self::Overview,
            UserRole::Editor => self::Basic,
            UserRole::Manager => self::Standard,
            UserRole::Admin, UserRole::SuperAdmin => self::Full,
        };
    }
}
