<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\Commercial\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\OfferResource;
use App\Models\Commercial\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $offers = Offer::query()
            ->where('status', OfferStatus::Active)
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            })
            ->where('valid_from', '<=', now())
            ->with(['sellableSku', 'channel'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Offers retrieved.',
            'data' => OfferResource::collection($offers),
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }

    public function show(Request $request, Offer $offer): JsonResponse
    {
        if ($offer->status !== OfferStatus::Active) {
            abort(404, 'Offer not found.');
        }

        $offer->load(['sellableSku', 'channel']);

        return $this->success(
            (new OfferResource($offer))->resolve(),
            'Offer retrieved.',
        );
    }
}
