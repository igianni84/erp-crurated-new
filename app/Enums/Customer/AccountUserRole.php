<?php

namespace App\Enums\Customer;

/**
 * Enum AccountUserRole
 *
 * Defines the role of a User within an Account.
 * Used to determine access permissions for Account operations.
 *
 * - Owner: Full control, cannot be removed
 * - Admin: Can manage users and all operations
 * - Operator: Can perform operations but not manage users
 * - Viewer: Read-only access
 */
enum AccountUserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    /**
     * Get the human-readable label for this role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Operator => 'Operator',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::Owner => 'danger',
            self::Admin => 'warning',
            self::Operator => 'primary',
            self::Viewer => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Owner => 'heroicon-o-star',
            self::Admin => 'heroicon-o-shield-check',
            self::Operator => 'heroicon-o-wrench',
            self::Viewer => 'heroicon-o-eye',
        };
    }

    /**
     * Get the description explaining the role's permissions.
     */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full control over the account. Cannot be removed.',
            self::Admin => 'Can manage users and perform all operations.',
            self::Operator => 'Can perform operations but cannot manage users.',
            self::Viewer => 'Read-only access to account information.',
        };
    }

    /**
     * Get the priority level (higher = more permissions).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Operator => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Check if this role can manage users (invite, change role, remove).
     */
    public function canManageUsers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Check if this role can perform operations (transactions, etc.).
     */
    public function canOperate(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Operator], true);
    }

    /**
     * Check if this role is read-only.
     */
    public function isReadOnly(): bool
    {
        return $this === self::Viewer;
    }

    /**
     * Check if this role is the owner role.
     */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Check if this role has at least the given role's permissions.
     */
    public function hasAtLeast(self $role): bool
    {
        return $this->priority() >= $role->priority();
    }
}
