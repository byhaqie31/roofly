<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends \App\Http\Controllers\Controller
{
    /** Raw Billplz webhook — store payload immediately, process async. See ADR-012. */
    public function billplz(Request $request): Response
    {
        // Store raw payload before any processing — every webhook, even malformed ones
        $webhook = PaymentWebhook::create([
            'payload' => $request->all(),
        ]);

        // TODO Phase 3: verify X-Signature header, dispatch ProcessBillplzWebhook job
        // For now: acknowledge receipt and let the job queue handle processing
        $webhook->update(['processed_at' => now()]);

        return response('OK', 200);
    }
}
