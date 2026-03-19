<?php

namespace App\Models\Customer;

use App\Enums\Customer\CustomerUserStatus;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * CustomerUser Model
 *
 * Bridge model for customer authentication via API.
 * A Customer (business entity) can have multiple CustomerUser logins.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property CustomerUserStatus $status
 * @property string|null $remember_token
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class CustomerUser extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\Customer\CustomerUserFactory> */
    use HasFactory;

    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerUserStatus::class,
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the customer that this user belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if this customer user is active.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerUserStatus::Active;
    }

    /**
     * Check if this customer user is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === CustomerUserStatus::Suspended;
    }

    /**
     * Check if this customer user is deactivated.
     */
    public function isDeactivated(): bool
    {
        return $this->status === CustomerUserStatus::Deactivated;
    }
}
