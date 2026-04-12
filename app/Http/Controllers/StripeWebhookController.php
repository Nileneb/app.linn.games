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
    private function handleCheckoutCompleted(object $session): void
    {
        $workspaceId = $session->metadata->workspace_id ?? null;
        $credits = (int) ($session->metadata->credits_cents ?? 0);

        if ($workspaceId && $credits > 0) {
            $workspace = Workspace::find($workspaceId);
            if ($workspace) {
                app(CreditService::class)->topUp($workspace, $credits, 'Stripe Checkout — '.$session->id);
                Log::info('Stripe topUp successful', [
                    'workspace_id' => $workspaceId,
                    'credits_cents' => $credits,
                    'stripe_session' => $session->id,
                ]);
            }
        }
    }

    private function handleSubscriptionActivated(object $sub): void
    {
        $workspaceId = $sub->metadata->workspace_id ?? null;
        if (! $workspaceId) {
            return;
        }

        Workspace::where('id', $workspaceId)->update([
            'mayring_subscription_id' => $sub->id,
            'mayring_active' => $sub->status === 'active',
            'stripe_customer_id' => $sub->customer,
        ]);

        Log::info('Mayring subscription activated', [
            'workspace_id' => $workspaceId,
            'subscription_id' => $sub->id,
            'status' => $sub->status,
        ]);
    }

    private function handleSubscriptionCancelled(object $sub): void
    {
        Workspace::where('mayring_subscription_id', $sub->id)
            ->update(['mayring_active' => false]);

        Log::info('Mayring subscription cancelled', ['subscription_id' => $sub->id]);
    }

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

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionActivated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionCancelled($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }
}
