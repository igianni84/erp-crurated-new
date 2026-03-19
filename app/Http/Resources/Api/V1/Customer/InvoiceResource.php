<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Finance\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type->value,
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'tax_amount' => (string) $this->tax_amount,
            'total_amount' => (string) $this->total_amount,
            'amount_paid' => (string) $this->amount_paid,
            'status' => $this->status->value,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'lines' => $this->when($this->relationLoaded('lines'), fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'line_total' => (string) $line->line_total,
                'tax_amount' => (string) $line->tax_amount,
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
