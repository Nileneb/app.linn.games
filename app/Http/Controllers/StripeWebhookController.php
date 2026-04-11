<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature invalid', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $workspaceId = $session->metadata->workspace_id ?? null;
            $credits = (int) ($session->metadata->credits_cents ?? 0);

            if ($workspaceId && $credits > 0) {
                $workspace = Workspace::find($workspaceId);
                if ($workspace) {
                    app(CreditService::class)->topUp(
                        $workspace,
                        $credits,
                        'Stripe Checkout — '.$session->id,
                    );

                    Log::info('Stripe topUp successful', [
                        'workspace_id' => $workspaceId,
                        'credits_cents' => $credits,
                        'stripe_session' => $session->id,
                    ]);
                }
            }
        }

        return response('OK', 200);
    }
}
