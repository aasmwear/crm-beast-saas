<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    /**
     * Download invoice PDF (portal client: only their own invoices).
     */
    public function download(Request $request, Invoice $invoice)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id) || (int) $invoice->client_id !== (int) $user->client_id) {
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
}
