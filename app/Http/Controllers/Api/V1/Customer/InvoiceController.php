<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\InvoiceResource;
use App\Models\Customer\CustomerUser;
use App\Models\Finance\Invoice;
use App\Services\Finance\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private InvoicePdfService $pdfService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null) {
            return $this->error('Customer not found.', 404);
        }

        $query = $customer->invoices()->with(['lines']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('invoice_type')) {
            $query->where('invoice_type', $request->input('invoice_type'));
        }

        $invoices = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Invoices retrieved.',
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $invoice->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        $invoice->load(['lines']);

        return $this->success(
            (new InvoiceResource($invoice))->resolve(),
            'Invoice retrieved.',
        );
    }

    public function downloadPdf(Request $request, Invoice $invoice): Response
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $invoice->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        if (! $this->pdfService->canGeneratePdf($invoice)) {
            abort(404, 'PDF not available for this invoice.');
        }

        return $this->pdfService->download($invoice);
    }
}
