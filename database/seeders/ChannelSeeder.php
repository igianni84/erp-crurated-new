<?php

namespace Database\Seeders;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Models\Commercial\Channel;
use Illuminate\Database\Seeder;

/**
 * ChannelSeeder - Creates commercial channels for sales
 *
 * Channels define how products are sold (B2C, B2B, Private Club)
 * and which commercial models are allowed (voucher_based, sell_through).
 */
class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $channels = [
            // B2C Channel - Main consumer-facing channel
            [
                'name' => 'Crurated B2C',
                'channel_type' => ChannelType::B2c,
                'default_currency' => 'EUR',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // B2C US Market
            [
                'name' => 'Crurated US',
                'channel_type' => ChannelType::B2c,
                'default_currency' => 'USD',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // B2C UK Market
            [
                'name' => 'Crurated UK',
                'channel_type' => ChannelType::B2c,
                'default_currency' => 'GBP',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // B2C Swiss Market
            [
                'name' => 'Crurated Switzerland',
                'channel_type' => ChannelType::B2c,
                'default_currency' => 'CHF',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // B2B Channel - Restaurant and hotel partners
            [
                'name' => 'Crurated Hospitality',
                'channel_type' => ChannelType::B2b,
                'default_currency' => 'EUR',
                'allowed_commercial_models' => ['voucher_based', 'sell_through'],
                'status' => ChannelStatus::Active,
            ],
            // B2B Channel - Wine merchants
            [
                'name' => 'Crurated Trade',
                'channel_type' => ChannelType::B2b,
                'default_currency' => 'EUR',
                'allowed_commercial_models' => ['sell_through'],
                'status' => ChannelStatus::Active,
            ],
            // Private Club - Exclusive member-only access
            [
                'name' => 'Crurated Collectors Club',
                'channel_type' => ChannelType::PrivateClub,
                'default_currency' => 'EUR',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // Private Club - Premium tier
            [
                'name' => 'Crurated Elite Circle',
                'channel_type' => ChannelType::PrivateClub,
                'default_currency' => 'EUR',
                'allowed_commercial_models' => ['voucher_based'],
                'status' => ChannelStatus::Active,
            ],
            // B2B Channel - Asia Pacific (inactive)
            [
                'name' => 'Crurated APAC Trade',
                'channel_type' => ChannelType::B2b,
                'default_currency' => 'USD',
                'allowed_commercial_models' => ['sell_through'],
                'status' => ChannelStatus::Inactive,
            ],
        ];

        foreach ($channels as $channelData) {
            Channel::firstOrCreate(
                ['name' => $channelData['name']],
                $channelData
            );
        }
    }
}
