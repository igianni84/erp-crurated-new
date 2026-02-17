<?php

namespace App\AI\Tools\Commercial;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Commercial\OfferStatus;
use App\Models\Commercial\Offer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActiveOffersTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get the list of currently active offers, optionally filtered by channel.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string(),
            'limit' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = (int) ($request['limit'] ?? 20);

        $query = Offer::query()
            ->where('status', OfferStatus::Active)
            ->with(['sellableSku.wineVariant.wineMaster', 'channel', 'priceBook']);

        if (isset($request['channel_id'])) {
            $query->where('channel_id', (string) $request['channel_id']);
        }

        $totalActive = (clone $query)->count();

        $offers = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $offerList = [];
        foreach ($offers as $offer) {
            $wineName = $offer->sellableSku->wineVariant->wineMaster->name ?? 'Unknown';

            $offerList[] = [
                'name' => $offer->name,
                'wine_name' => $wineName,
                'channel_name' => $offer->channel !== null ? $offer->channel->name : 'Unknown',
                'offer_type' => $offer->offer_type->label(),
                'valid_from' => $this->formatDate($offer->valid_from),
                'valid_to' => $offer->valid_to !== null ? $this->formatDate($offer->valid_to) : null,
                'visibility' => $offer->visibility->label(),
            ];
        }

        return (string) json_encode([
            'total_active' => $totalActive,
            'offers' => $offerList,
        ]);
    }
}
