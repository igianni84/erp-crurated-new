<?php

namespace App\Models\Customer;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Party Model
 *
 * Represents the base identity for all counterparties in the system.
 * A Party can be an individual person or a legal entity (company).
 * Parties can have multiple roles (customer, supplier, producer, partner).
 *
 * @property string $id
 * @property string $legal_name
 * @property PartyType $party_type
 * @property string|null $tax_id
 * @property string|null $vat_number
 * @property string|null $jurisdiction
 * @property PartyStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Party extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'parties';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'legal_name',
        'party_type',
        'tax_id',
        'vat_number',
        'jurisdiction',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'status' => PartyStatus::class,
        ];
    }

    /**
     * Get the roles for this party.
     *
     * @return HasMany<PartyRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    /**
     * Get the supplier/producer config for this party.
     * This is an optional one-to-one relationship - not all parties have a config.
     *
     * @return HasOne<\App\Models\Procurement\ProducerSupplierConfig, $this>
     */
    public function supplierConfig(): HasOne
    {
        return $this->hasOne(\App\Models\Procurement\ProducerSupplierConfig::class, 'party_id');
    }

    /**
     * Get the purchase orders where this party is the supplier.
     *
     * @return HasMany<\App\Models\Procurement\PurchaseOrder, $this>
     */
    public function purchaseOrdersAsSupplier(): HasMany
    {
        return $this->hasMany(\App\Models\Procurement\PurchaseOrder::class, 'supplier_party_id');
    }

    /**
     * Check if the party has a supplier/producer config.
     */
    public function hasSupplierConfig(): bool
    {
        return $this->supplierConfig()->exists();
    }

    /**
     * Get the audit logs for this party.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the party is active.
     */
    public function isActive(): bool
    {
        return $this->status === PartyStatus::Active;
    }

    /**
     * Check if the party is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === PartyStatus::Inactive;
    }

    /**
     * Check if the party is an individual.
     */
    public function isIndividual(): bool
    {
        return $this->party_type === PartyType::Individual;
    }

    /**
     * Check if the party is a legal entity.
     */
    public function isLegalEntity(): bool
    {
        return $this->party_type === PartyType::LegalEntity;
    }

    /**
     * Check if the party has a specific role.
     */
    public function hasRole(PartyRoleType|string $role): bool
    {
        $roleValue = $role instanceof PartyRoleType ? $role->value : $role;

        return $this->roles()->where('role', $roleValue)->exists();
    }

    /**
     * Add a role to this party.
     *
     * @throws \Illuminate\Database\QueryException if role already exists (unique constraint)
     */
    public function addRole(PartyRoleType $role): PartyRole
    {
        return $this->roles()->create([
            'role' => $role,
        ]);
    }

    /**
     * Remove a role from this party.
     */
    public function removeRole(PartyRoleType $role): bool
    {
        return $this->roles()->where('role', $role->value)->delete() > 0;
    }

    /**
     * Check if the party is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(PartyRoleType::Customer);
    }

    /**
     * Check if the party is a supplier.
     */
    public function isSupplier(): bool
    {
        return $this->hasRole(PartyRoleType::Supplier);
    }

    /**
     * Check if the party is a producer.
     */
    public function isProducer(): bool
    {
        return $this->hasRole(PartyRoleType::Producer);
    }

    /**
     * Check if the party is a partner.
     */
    public function isPartner(): bool
    {
        return $this->hasRole(PartyRoleType::Partner);
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the party type color for UI display.
     */
    public function getPartyTypeColor(): string
    {
        return $this->party_type->color();
    }

    /**
     * Get the party type label for UI display.
     */
    public function getPartyTypeLabel(): string
    {
        return $this->party_type->label();
    }
}
