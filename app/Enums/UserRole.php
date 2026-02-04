<?php

namespace App\Enums;

/**
 * Enum UserRole
 *
 * User roles for access control in the Crurated ERP.
 * Roles are ordered by permission level (highest to lowest).
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * Get the human-readable label for this role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Get the color for UI display (Filament-compatible).
     */
    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Manager => 'primary',
            self::Editor => 'success',
            self::Viewer => 'gray',
        };
    }

    /**
     * Get the icon for UI display (Filament-compatible).
     */
    public function icon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-key',
            self::Manager => 'heroicon-o-briefcase',
            self::Editor => 'heroicon-o-pencil-square',
            self::Viewer => 'heroicon-o-eye',
        };
    }

    /**
     * Get the permission level for this role (higher = more permissions).
     */
    public function level(): int
    {
        return match ($this) {
            self::SuperAdmin => 100,
            self::Admin => 80,
            self::Manager => 60,
            self::Editor => 40,
            self::Viewer => 20,
        };
    }

    /**
     * Check if this role has at least the given role's permissions.
     */
    public function hasAtLeast(UserRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Check if this role can manage users.
     */
    public function canManageUsers(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Check if this role can create/edit content.
     */
    public function canEdit(): bool
    {
        return $this->hasAtLeast(self::Editor);
    }

    /**
     * Check if this role is read-only.
     */
    public function isReadOnly(): bool
    {
        return $this === self::Viewer;
    }

    /**
     * Check if this role can consume committed inventory (exceptional operation).
     *
     * This is an exceptional permission for consuming inventory that is
     * committed to voucher fulfillment. Only Admin and SuperAdmin roles
     * have this permission as it requires explicit justification and
     * creates InventoryException records for finance & ops review.
     */
    public function canConsumeCommittedInventory(): bool
    {
        return $this->hasAtLeast(self::Admin);
    }
}
