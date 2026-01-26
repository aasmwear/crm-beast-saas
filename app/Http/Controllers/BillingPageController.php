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

        return Inertia::render('Billing/Manage', [
            'organization' => $organization->only(['id', 'slug', 'name']),
        ]);
    }
}
