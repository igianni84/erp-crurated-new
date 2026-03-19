<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Features\CatalogSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CatalogSearchRequest;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\CatalogSearchResultResource;
use App\Services\Pim\CatalogSearchService;
use Illuminate\Http\JsonResponse;
use Laravel\Pennant\Feature;

class CatalogSearchController extends Controller
{
    use ApiResponseTrait;

    public function __invoke(CatalogSearchRequest $request, CatalogSearchService $service): JsonResponse
    {
        if (! Feature::active(CatalogSearch::class)) {
            return $this->error('Catalog search is currently unavailable.', 503);
        }

        $results = $service->searchCatalog(
            query: $request->validated('q'),
            filters: $request->only(['country', 'region', 'producer', 'appellation', 'vintage_min', 'vintage_max', 'format']),
            sort: $request->validated('sort'),
            perPage: (int) $request->validated('per_page', 20),
            page: (int) $request->validated('page', 1),
        );

        return response()->json([
            'success' => true,
            'message' => 'Catalog search results.',
            'data' => CatalogSearchResultResource::collection($results),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }
}
