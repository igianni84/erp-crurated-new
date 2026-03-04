<?php

namespace Database\Factories\Allocation;

use App\Enums\Allocation\VoucherTransferStatus;
use App\Models\Allocation\Voucher;
use App\Models\Allocation\VoucherTransfer;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherTransfer>
 */
class VoucherTransferFactory extends Factory
{
    protected $model = VoucherTransfer::class;

    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'from_customer_id' => Customer::factory(),
            'to_customer_id' => Customer::factory(),
            'status' => VoucherTransferStatus::Pending,
            'initiated_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoucherTransferStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoucherTransferStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
