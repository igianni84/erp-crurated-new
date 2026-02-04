<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PartyRole Model (Stub)
 *
 * Represents a role assigned to a party.
 * Full implementation will be in US-002.
 *
 * @property string $id
 * @property string $party_id
 * @property string $role
 */
class PartyRole extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'party_roles';

    /**
     * Get the party that owns this role.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
