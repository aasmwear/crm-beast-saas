<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    /**
     * Generate next invoice number for the organization (e.g. INV-2026-001).
     */
    private function nextInvoiceNumber(Organization $organization): string
    {
        $year = date('Y');
        $prefix = "INV-{$year}-";
        $last = Invoice::where('organization_id', $organization->id)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('number');
        $seq = 1;
        if ($last && preg_match('/' . preg_quote($prefix, '/') . '(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->with(['client:id,company_name', 'project:id,title'])
            ->orderByDesc('issue_date')
            ->paginate(15);

        return Inertia::render('Invoices/Index', [
            'organizationSlug' => $organization->slug,
            'invoices' => $invoices,
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', Invoice::class);

        $clients = Client::query()
            ->where('organization_id', $organization->id)
            ->orderBy('company_name')
            ->limit(200)
            ->get(['id', 'company_name', 'currency']);

        $projects = Project::query()
            ->where('organization_id', $organization->id)
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'client_id', 'title']);

        return Inertia::render('Invoices/Create', [
            'organizationSlug' => $organization->slug,
            'clients' => $clients,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $request->merge([
            'project_id' => $request->input('project_id') ?: null,
        ]);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);

        $client = Client::where('id', $validated['client_id'])->where('organization_id', $organization->id)->firstOrFail();
        $currency = $client->currency ?? 'USD';

        if (! empty($validated['project_id'])) {
            $project = Project::where('id', $validated['project_id'])->where('client_id', $client->id)->first();
            if (! $project) {
                $validated['project_id'] = null;
            }
        } else {
            $validated['project_id'] = null;
        }

        $invoice = new Invoice([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'project_id' => $validated['project_id'],
            'number' => $this->nextInvoiceNumber($organization),
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'status' => 'Draft',
            'total_cents' => 0,
            'currency' => $currency,
        ]);
        $invoice->save();

        return redirect()->route('invoices.show', [
            'organization' => $organization->slug,
            'invoice' => $invoice->id,
        ])->with('success', 'Invoice created.');
    }

    public function show(Organization $organization, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        if ((int) $invoice->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $invoice->load(['client:id,company_name,address,currency', 'project:id,title', 'items']);

        return Inertia::render('Invoices/Show', [
            'organizationSlug' => $organization->slug,
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, Organization $organization, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ((int) $invoice->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit_price_cents' => ['required_with:items', 'integer', 'min:0'],
        ]);

        if (isset($validated['issue_date'])) {
            $invoice->issue_date = $validated['issue_date'];
        }
        if (isset($validated['due_date'])) {
            $invoice->due_date = $validated['due_date'];
        }
        if (array_key_exists('notes', $validated)) {
            $invoice->notes = $validated['notes'];
        }
        $invoice->save();

        if (isset($validated['items'])) {
            $existingIds = [];
            foreach ($validated['items'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPriceCents = (int) $item['unit_price_cents'];
                $amountCents = InvoiceItem::computeAmount($quantity, $unitPriceCents);
                if (! empty($item['id'])) {
                    $existing = InvoiceItem::where('invoice_id', $invoice->id)->where('id', $item['id'])->first();
                    if ($existing) {
                        $existing->update([
                            'description' => $item['description'],
                            'quantity' => $quantity,
                            'unit_price_cents' => $unitPriceCents,
                            'amount_cents' => $amountCents,
                        ]);
                        $existingIds[] = $existing->id;
                        continue;
                    }
                }
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'amount_cents' => $amountCents,
                ]);
            }
            $invoice->items()->whereNotIn('id', $existingIds)->delete();
            $invoice->recalculateTotal();
        }

        return back()->with('success', 'Invoice updated.');
    }

    public function download(Organization $organization, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if ((int) $invoice->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $invoice->load(['client', 'project', 'items']);

        $html = view('invoices.pdf', ['invoice' => $invoice])->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return $pdf->download('invoice-' . preg_replace('/[^a-z0-9\-]/i', '-', $invoice->number) . '.pdf');
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->number . '.html"',
        ]);
    }

    public function markSent(Organization $organization, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        if ((int) $invoice->organization_id !== (int) $organization->id) {
            abort(404);
        }
        $invoice->update(['status' => 'Sent']);
        return back()->with('success', 'Invoice marked as Sent.');
    }

    public function markPaid(Organization $organization, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        if ((int) $invoice->organization_id !== (int) $organization->id) {
            abort(404);
        }
        $invoice->update(['status' => 'Paid']);
        return back()->with('success', 'Invoice marked as Paid.');
    }
}
