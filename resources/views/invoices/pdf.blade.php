<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice {{ $invoice->number }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
    .header { display: table; width: 100%; margin-bottom: 24px; }
    .header-left { display: table-cell; width: 50%; }
    .header-right { display: table-cell; width: 50%; text-align: right; }
    h1 { font-size: 24px; margin: 0 0 8px 0; }
    .meta { color: #6b7280; font-size: 11px; }
    table.items { width: 100%; border-collapse: collapse; margin: 24px 0; }
    table.items th { text-align: left; padding: 10px 8px; border-bottom: 2px solid #e5e7eb; }
    table.items td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; }
    table.items .qty, .price, .amount { text-align: right; }
    .total-row { font-weight: bold; font-size: 14px; }
    .total-row td { border-top: 2px solid #1f2937; padding-top: 12px; }
    .notes { margin-top: 24px; padding: 12px; background: #f9fafb; border-radius: 4px; }
    .status { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .status-Draft { background: #fef3c7; color: #92400e; }
    .status-Sent { background: #dbeafe; color: #1e40af; }
    .status-Paid { background: #d1fae5; color: #065f46; }
    .status-Overdue { background: #fee2e2; color: #991b1b; }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-left">
      <h1>Invoice {{ $invoice->number }}</h1>
      <p class="meta">Issue date: {{ $invoice->issue_date->format('M j, Y') }} &nbsp; Due date: {{ $invoice->due_date->format('M j, Y') }}</p>
      <span class="status status-{{ $invoice->status }}">{{ $invoice->status }}</span>
    </div>
    <div class="header-right">
      <strong>Bill To</strong><br>
      {{ $invoice->client->company_name }}<br>
      @if($invoice->client->address){{ $invoice->client->address }}<br>@endif
    </div>
  </div>

  @if($invoice->project)
  <p><strong>Project:</strong> {{ $invoice->project->title }}</p>
  @endif

  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="qty">Qty</th>
        <th class="price">Unit Price</th>
        <th class="amount">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->items as $item)
      <tr>
        <td>{{ $item->description }}</td>
        <td class="qty">{{ number_format($item->quantity, 2) }}</td>
        <td class="price">{{ number_format($item->unit_price_cents / 100, 2) }} {{ $invoice->currency }}</td>
        <td class="amount">{{ number_format($item->amount_cents / 100, 2) }} {{ $invoice->currency }}</td>
      </tr>
      @endforeach
      <tr class="total-row">
        <td colspan="3" class="price">Total</td>
        <td class="amount">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
      </tr>
    </tbody>
  </table>

  @if($invoice->notes)
  <div class="notes">
    <strong>Notes</strong><br>
    {!! nl2br(e($invoice->notes)) !!}
  </div>
  @endif
</body>
</html>
