<?php

namespace App\DataTransferObjects\Commercial;

use App\Models\Commercial\Channel;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use Carbon\Carbon;

/**
 * Simulation input context.
 */
class SimulationContext
{
    public function __construct(
        public readonly ?SellableSku $sellableSku,
        public readonly ?Channel $channel,
        public readonly ?Customer $customer,
        public readonly Carbon $date,
        public readonly int $quantity,
    ) {}

    /**
     * Get SKU display label.
     */
    public function getSkuLabel(): string
    {
        if ($this->sellableSku === null) {
            return 'Unknown SKU';
        }

        $wineVariant = $this->sellableSku->wineVariant;
        if ($wineVariant === null) {
            return $this->sellableSku->sku_code;
        }

        $wineMaster = $wineVariant->wineMaster;
        $wineName = $wineMaster !== null ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant->vintage_year ?? 'NV';
        $format = $this->sellableSku->format !== null ? $this->sellableSku->format->volume_ml.'ml' : '';
        $caseConfig = $this->sellableSku->caseConfiguration;
        $packaging = $caseConfig !== null ? $caseConfig->bottles_per_case.'x' : '';

        return "{$this->sellableSku->sku_code} - {$wineName} {$vintage} ({$format} {$packaging})";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->getSkuLabel(),
            'sku_code' => $this->sellableSku !== null ? $this->sellableSku->sku_code : 'N/A',
            'sku_id' => $this->sellableSku?->id,
            'channel' => $this->channel !== null ? $this->channel->name : 'Unknown Channel',
            'channel_id' => $this->channel?->id,
            'customer' => $this->customer !== null ? $this->customer->name : 'Anonymous',
            'customer_id' => $this->customer?->id,
            'date' => $this->date->format('Y-m-d'),
            'quantity' => $this->quantity,
        ];
    }
}
