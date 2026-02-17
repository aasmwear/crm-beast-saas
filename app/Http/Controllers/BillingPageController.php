<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BillingPageController extends Controller
{
    public function index(Organization $organization): InertiaResponse
    {
        if (! auth()->check()) {
            abort(403);
        }

        $currentPlan = 'Free Trial';
        $nextPayment = '2026-03-01';
        $trialEndsAt = '2026-03-01';

        $invoices = [
            [
                'id' => 1,
                'date' => '2026-01-01',
                'invoice_number' => 'INV-2026-001',
                'amount' => 0,
                'status' => 'Paid',
                'pdf_url' => '#',
            ],
            [
                'id' => 2,
                'date' => '2025-12-01',
                'invoice_number' => 'INV-2025-012',
                'amount' => 0,
                'status' => 'Paid',
                'pdf_url' => '#',
            ],
            [
                'id' => 3,
                'date' => '2025-11-01',
                'invoice_number' => 'INV-2025-011',
                'amount' => 0,
                'status' => 'Paid',
                'pdf_url' => '#',
            ],
        ];

        $plans = [
            [
                'id' => 'basic',
                'name' => 'Basic',
                'price' => 29,
                'interval' => 'month',
                'features' => [
                    'Up to 5 team members',
                    '10 projects',
                    '1 GB storage',
                    'Email support',
                ],
                'recommended' => false,
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 79,
                'interval' => 'month',
                'features' => [
                    'Up to 25 team members',
                    'Unlimited projects',
                    '10 GB storage',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                ],
                'recommended' => true,
            ],
            [
                'id' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 199,
                'interval' => 'month',
                'features' => [
                    'Unlimited team members',
                    'Unlimited projects',
                    '100 GB storage',
                    'Dedicated support',
                    'Custom integrations',
                    'SSO & audit logs',
                    'SLA guarantee',
                ],
                'recommended' => false,
            ],
        ];

        return Inertia::render('Billing/Index', [
            'organization' => $organization->only(['id', 'slug', 'name']),
            'currentPlan' => $currentPlan,
            'nextPayment' => $nextPayment,
            'trialEndsAt' => $trialEndsAt,
            'invoices' => $invoices,
            'plans' => $plans,
        ]);
    }
}
