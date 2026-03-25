<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgDailyMetric extends Model
{
    protected $table = 'org_daily_metrics';

    protected $fillable = [
        'organization_id',
        'metric_date',
        'clients_count',
        'projects_count',
        'tasks_count',
        'open_tasks_count',
        'attendance_count',
        'activities_count',
        'invoices_count',
        'revenue_cents',
        'outstanding_cents',
        'users_count',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'clients_count' => 'integer',
        'projects_count' => 'integer',
        'tasks_count' => 'integer',
        'open_tasks_count' => 'integer',
        'attendance_count' => 'integer',
        'activities_count' => 'integer',
        'invoices_count' => 'integer',
        'revenue_cents' => 'integer',
        'outstanding_cents' => 'integer',
        'users_count' => 'integer',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
