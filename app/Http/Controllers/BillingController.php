<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BillingController extends Controller
{
    public function subscribe(Request $request): InertiaResponse
    {
        $user = $request->user();
        $hasStripe = (bool) (config('cashier.key') && config('cashier.secret'));

        return Inertia::render('Billing/Subscribe', [
            'hasStripe' => $hasStripe,
            'customer' => $hasStripe ? ['email' => (string) $user->email] : null,
            'plans' => [
                ['handle' => 'starter', 'name' => 'Starter', 'price' => '$0', 'features' => ['Up to 3 clients', 'Basic boards']],
                ['handle' => 'pro',     'name' => 'Pro',     'price' => '$19', 'features' => ['Unlimited clients', 'Advanced boards', 'CSV export']],
            ],
        ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! (config('cashier.key') && config('cashier.secret'))) {
            return redirect()->back()->with('error', 'Stripe is not configured. Set CASHIER_KEY/CASHIER_SECRET.');
        }

        return $user->redirectToBillingPortal(route('tenant.dashboard', ['organization' => $user->organizations()->value('slug') ?: 'acme']));
    }
}
