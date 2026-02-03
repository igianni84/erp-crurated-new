<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\ReservationContextType;
use App\Enums\Allocation\ReservationStatus;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * TemporaryReservation Model
 *
 * Represents a temporary hold on allocation quantity to prevent overselling
 * during checkout, negotiations, or manual holds.
 *
 * Note: Reservations do NOT consume allocation, they only "block" quantity temporarily.
 *
 * @property string $id
 * @property string $allocation_id
 * @property int $quantity
 * @property ReservationContextType $context_type
 * @property string|null $context_reference
 * @property ReservationStatus $status
 * @property \Carbon\Carbon $expires_at
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TemporaryReservation extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'temporary_reservations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'allocation_id',
        'quantity',
        'context_type',
        'context_reference',
        'status',
        'expires_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'context_type' => ReservationContextType::class,
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TemporaryReservation $reservation): void {
            // Ensure quantity is positive
            if ($reservation->quantity <= 0) {
                throw new \InvalidArgumentException(
                    'Reservation quantity must be greater than zero'
                );
            }
        });
    }

    /**
     * Get the allocation for this reservation.
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the audit logs for this reservation.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the reservation is still active.
     */
    public function isActive(): bool
    {
        return $this->status === ReservationStatus::Active;
    }

    /**
     * Check if the reservation has expired (based on time).
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the reservation should be marked as expired.
     * (Is active but past expiration time)
     */
    public function shouldExpire(): bool
    {
        return $this->isActive() && $this->hasExpired();
    }

    /**
     * Check if the reservation is in a terminal status.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Mark the reservation as expired.
     */
    public function expire(): bool
    {
        if (! $this->status->canTransitionTo(ReservationStatus::Expired)) {
            return false;
        }

        $this->status = ReservationStatus::Expired;

        return $this->save();
    }

    /**
     * Cancel the reservation.
     */
    public function cancel(): bool
    {
        if (! $this->status->canTransitionTo(ReservationStatus::Cancelled)) {
            return false;
        }

        $this->status = ReservationStatus::Cancelled;

        return $this->save();
    }

    /**
     * Convert the reservation (when sale is confirmed).
     */
    public function convert(): bool
    {
        if (! $this->status->canTransitionTo(ReservationStatus::Converted)) {
            return false;
        }

        $this->status = ReservationStatus::Converted;

        return $this->save();
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
     * Get the context type label for UI display.
     */
    public function getContextTypeLabel(): string
    {
        return $this->context_type->label();
    }

    /**
     * Scope to get only active reservations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TemporaryReservation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TemporaryReservation>
     */
    public function scopeActive($query)
    {
        return $query->where('status', ReservationStatus::Active);
    }

    /**
     * Scope to get expired reservations that need status update.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TemporaryReservation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TemporaryReservation>
     */
    public function scopeNeedsExpiration($query)
    {
        return $query
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '<', now());
    }
}
