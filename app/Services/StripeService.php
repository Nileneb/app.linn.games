<?php

namespace App\Services;

use App\Models\Workspace;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function ensureCustomer(Workspace $workspace): string
    {
        if ($workspace->stripe_customer_id) {
            return $workspace->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'name' => $workspace->name,
            'email' => $workspace->owner?->email,
            'metadata' => ['workspace_id' => $workspace->id],
        ]);

        $workspace->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    public function createCheckoutSession(Workspace $workspace, int $packageIndex): string
    {
        $packages = config('services.stripe.packages');
        $package = $packages[$packageIndex] ?? $packages[1];

        $customerId = $this->ensureCustomer($workspace);

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => config('services.stripe.currency'),
                    'unit_amount' => $package['price_eur'],
                    'product_data' => [
                        'name' => 'app.linn.games Credits — '.$package['label'],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('credits.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('credits.purchase'),
            'metadata' => [
                'workspace_id' => $workspace->id,
                'credits_cents' => $package['cents'],
            ],
        ]);

        return $session->url;
    }

    public function createMayringSubscriptionCheckout(Workspace $workspace, string $successUrl, string $cancelUrl): string
    {
        $customerId = $this->ensureCustomer($workspace);

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => config('services.stripe.mayring_price_id'),
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'allow_promotion_codes' => true,
            'metadata' => ['workspace_id' => $workspace->id],
        ]);

        return $session->url;
    }

    public function cancelMayringSubscription(Workspace $workspace): void
    {
        if (! $workspace->mayring_subscription_id) {
            return;
        }

        $this->stripe->subscriptions->cancel($workspace->mayring_subscription_id);
    }
}
