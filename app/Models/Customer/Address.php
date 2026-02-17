<?php

namespace App\Models\Customer;

use App\Enums\Customer\AddressType;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Address Model
 *
 * Represents a billing or shipping address attached to a Customer or other addressable entity.
 *
 * @property string $id
 * @property string $addressable_type
 * @property string $addressable_id
 * @property AddressType $type
 * @property string $line_1
 * @property string|null $line_2
 * @property string $city
 * @property string|null $state
 * @property string $postal_code
 * @property string $country
 * @property bool $is_default
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Address extends Model
{
    use Auditable;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'addresses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'type',
        'line_1',
        'line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the parent addressable model (Customer, Account, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the audit logs for this address.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if this is a billing address.
     */
    public function isBilling(): bool
    {
        return $this->type === AddressType::Billing;
    }

    /**
     * Check if this is a shipping address.
     */
    public function isShipping(): bool
    {
        return $this->type === AddressType::Shipping;
    }

    /**
     * Check if this is a default address.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Get the type label for UI display.
     */
    public function getTypeLabel(): string
    {
        return $this->type->label();
    }

    /**
     * Get the type color for UI display.
     */
    public function getTypeColor(): string
    {
        return $this->type->color();
    }

    /**
     * Get the type icon for UI display.
     */
    public function getTypeIcon(): string
    {
        return $this->type->icon();
    }

    /**
     * Get the formatted full address.
     */
    public function getFormattedAddress(): string
    {
        $parts = [
            $this->line_1,
        ];

        if ($this->line_2) {
            $parts[] = $this->line_2;
        }

        $cityLine = $this->city;
        if ($this->state) {
            $cityLine .= ', '.$this->state;
        }
        $cityLine .= ' '.$this->postal_code;
        $parts[] = $cityLine;

        $parts[] = $this->country;

        return implode("\n", $parts);
    }

    /**
     * Get a one-line summary of the address.
     */
    public function getOneLine(): string
    {
        $parts = [$this->line_1, $this->city, $this->country];

        return implode(', ', $parts);
    }

    /**
     * Set this address as the default for its type.
     * Unsets any other default addresses of the same type for the same addressable.
     */
    public function setAsDefault(): void
    {
        // Unset other defaults of the same type for this addressable
        static::query()
            ->where('addressable_type', $this->addressable_type)
            ->where('addressable_id', $this->addressable_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}
