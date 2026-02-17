<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property float $quantity
 * @property int $unit_price_cents
 * @property int $amount_cents
 */
final class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_cents',
        'amount_cents',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function computeAmount(float $quantity, int $unitPriceCents): int
    {
        return (int) round($quantity * $unitPriceCents);
    }
}
