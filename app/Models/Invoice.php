<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $client_id
 * @property int|null $project_id
 * @property string $number
 * @property \Illuminate\Support\Carbon $issue_date
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $status
 * @property int $total_cents
 * @property string $currency
 * @property string|null $notes
 * @property string|null $stripe_payment_intent_id
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
final class Invoice extends Model
{
    protected $fillable = [
        'organization_id',
        'client_id',
        'project_id',
        'number',
        'issue_date',
        'due_date',
        'status',
        'total_cents',
        'currency',
        'notes',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function recalculateTotal(): void
    {
        $this->total_cents = (int) $this->items()->sum('amount_cents');
        $this->save();
    }
}
