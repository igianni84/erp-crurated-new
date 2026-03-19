<?php

namespace App\Http\Middleware;

use App\Features\CustomerApi;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class CustomerApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Feature::active(CustomerApi::class)) {
            return response()->json([
                'success' => false,
                'message' => 'The Customer API is currently unavailable.',
            ], 503);
        }

        return $next($request);
    }
}
