<?php

namespace App\Models\Customer;

use App\Enums\Customer\PartyRoleType;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * PartyRole Model
 *
 * Represents a role assigned to a party.
 * A party can have multiple roles (customer, supplier, producer, partner).
 * The combination of party_id and role must be unique.
 *
 * @property string $id
 * @property string $party_id
 * @property PartyRoleType $role
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PartyRole extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'party_roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PartyRoleType::class,
        ];
    }

    /**
     * Get the party that owns this role.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Get the audit logs for this party role.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Get the role label for UI display.
     */
    public function getRoleLabel(): string
    {
        return $this->role->label();
    }

    /**
     * Get the role color for UI display.
     */
    public function getRoleColor(): string
    {
        return $this->role->color();
    }

    /**
     * Get the role icon for UI display.
     */
    public function getRoleIcon(): string
    {
        return $this->role->icon();
    }

    /**
     * Check if this role is of a specific type.
     */
    public function isRole(PartyRoleType $type): bool
    {
        return $this->role === $type;
    }

    /**
     * Check if this is a customer role.
     */
    public function isCustomer(): bool
    {
        return $this->role === PartyRoleType::Customer;
    }

    /**
     * Check if this is a supplier role.
     */
    public function isSupplier(): bool
    {
        return $this->role === PartyRoleType::Supplier;
    }

    /**
     * Check if this is a producer role.
     */
    public function isProducer(): bool
    {
        return $this->role === PartyRoleType::Producer;
    }

    /**
     * Check if this is a partner role.
     */
    public function isPartner(): bool
    {
        return $this->role === PartyRoleType::Partner;
    }
}
