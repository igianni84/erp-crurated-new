<?php

namespace App\Http\Middleware;

use App\Enums\Customer\BlockStatus;
use App\Enums\Customer\CustomerUserStatus;
use App\Models\Customer\CustomerUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Reset default guard to 'web' to prevent FK violation in Auditable trait.
        // Auditable uses Auth::id() for created_by/updated_by (FK → users table).
        // Without this reset, Auth::id() would return a CustomerUser UUID after
        // Sanctum sets the guard to 'customer', causing an FK constraint violation.
        Auth::shouldUse('web');

        /** @var CustomerUser|null $customerUser */
        $customerUser = $request->user('customer');

        if ($customerUser === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check CustomerUser status
        if ($customerUser->status !== CustomerUserStatus::Active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been '.$customerUser->status->value.'. Please contact support.',
            ], 403);
        }

        // Check Customer status (must be active)
        $customer = $customerUser->customer;

        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account not found.',
            ], 403);
        }

        if (! $customer->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your customer account is '.$customer->status->value.'. Please contact support.',
            ], 403);
        }

        // Check for active compliance blocks
        $complianceBlock = $customer->operationalBlocks()
            ->where('status', BlockStatus::Active)
            ->where('block_type', 'compliance')
            ->first();

        if ($complianceBlock !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has a compliance restriction. Reason: '.$complianceBlock->reason,
            ], 403);
        }

        return $next($request);
    }
}
