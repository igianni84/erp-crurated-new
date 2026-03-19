<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\XeroApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XeroAuthController extends Controller
{
    public function __construct(
        protected XeroApiClient $xeroClient
    ) {}

    /**
     * Redirect to Xero OAuth2 authorization page.
     */
    public function authorize(): RedirectResponse
    {
        if (! $this->xeroClient->isConfigured()) {
            return redirect()->route('filament.admin.pages.xero-connection')
                ->with('error', 'Xero OAuth credentials are not configured. Set XERO_CLIENT_ID and XERO_CLIENT_SECRET.');
        }

        return redirect()->away($this->xeroClient->getAuthorizationUrl());
    }

    /**
     * Handle the OAuth2 callback from Xero.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            Log::channel('finance')->error('Xero OAuth callback error', [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            return redirect()->route('filament.admin.pages.xero-connection')
                ->with('error', 'Xero authorization was denied: '.$request->input('error_description', 'Unknown error'));
        }

        $code = $request->input('code');

        if (empty($code)) {
            return redirect()->route('filament.admin.pages.xero-connection')
                ->with('error', 'No authorization code received from Xero.');
        }

        try {
            $this->xeroClient->exchangeCodeForToken((string) $code);

            return redirect()->route('filament.admin.pages.xero-connection')
                ->with('success', 'Successfully connected to Xero!');
        } catch (\Throwable $e) {
            Log::channel('finance')->error('Xero OAuth token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('filament.admin.pages.xero-connection')
                ->with('error', 'Failed to connect to Xero: '.$e->getMessage());
        }
    }
}
