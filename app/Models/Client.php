<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ClientFactory>
 *
 * @method static \Database\Factories\ClientFactory factory($count = null, $state = [])
 *
 * @property int $id
 * @property int $organization_id
 * @property string $company_name
 * @property-read \App\Models\Organization $organization
 */
class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'company_name',
        'industry',
        'niche',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'website',
        'address',
        'tags',
        'fronter',
        'closer',
        'assigned_account_manager_id',
        'google_business_profile_status',
        'google_business_profile_access_status',
        'client_activation_status',
        'notes_by_cst',
        'notes_by_sales',
        'notes_by_tech',
        'status',
        'search_vector',
    ];

    protected $casts = [
        'tags' => 'array',
        'fronter' => 'array',
        'closer' => 'array',
    ];

    /**
     * @return BelongsTo<\App\Models\Organization, \App\Models\Client>
     */
    public function organization(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\Organization, \App\Models\Client> $rel */
        $rel = $this->belongsTo(Organization::class);

        return $rel;
    }

    /**
     * @return HasMany<\App\Models\Project, \App\Models\Client>
     */
    public function projects(): HasMany
    {
        /** @var HasMany<\App\Models\Project, \App\Models\Client> $rel */
        $rel = $this->hasMany(Project::class);

        return $rel;
    }

    /**
     * @return BelongsTo<\App\Models\User, \App\Models\Client>
     */
    public function assignedAccountManager(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\User, \App\Models\Client> $rel */
        $rel = $this->belongsTo(User::class, 'assigned_account_manager_id');

        return $rel;
    }

    /**
     * Back-compat alias so older code that calls $client->accountManager still works.
     *
     * @return BelongsTo<\App\Models\User, \App\Models\Client>
     */
    public function accountManager(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\User, \App\Models\Client> $rel */
        $rel = $this->belongsTo(User::class, 'assigned_account_manager_id');

        return $rel;
    }

    public function getAccountManagerIdAttribute(): ?int
    {
        return $this->assigned_account_manager_id;
    }

    public function setAccountManagerIdAttribute(?int $value): void
    {
        $this->attributes['assigned_account_manager_id'] = $value;
    }

    /**
     * @param  Builder<\App\Models\Client>  $q
     * @return Builder<\App\Models\Client>
     */
    public function scopeForOrg(Builder $q, Organization|int $org): Builder
    {
        $orgId = $org instanceof Organization ? $org->id : $org;

        return $q->where('organization_id', $orgId);
    }

    /**
     * Visible if:
     *  - Admin/manager in the org, or
     *  - Assigned account manager, or
     *  - Fronter/closer, or
     *  - Has tasks on any project for this client.
     *
     * @param  Builder<\App\Models\Client>  $q
     * @return Builder<\App\Models\Client>
     */
    public function scopeVisibleTo(Builder $q, User $user, Organization $org): Builder
    {
        if ($user->hasRole(['Admin', 'HR', 'DepartmentHead'], $org)) {
            return $q->forOrg($org);
        }

        return $q->forOrg($org)->where(function ($qq) use ($user) {
            $qq->where('assigned_account_manager_id', $user->id)
                ->orWhereJsonContains('fronter', $user->id)
                ->orWhereJsonContains('closer', $user->id)
                ->orWhereExists(function ($sub) use ($user) {
                    $sub->from('projects')
                        ->join('tasks', 'tasks.project_id', '=', 'projects.id')
                        ->whereColumn('projects.client_id', 'clients.id')
                        ->whereJsonContains('tasks.assignees', (string) $user->id);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Client>  $q
     * @return Builder<\App\Models\Client>
     */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        $like = '%'.strtolower($term).'%';

        return $q->where(function ($qq) use ($like) {
            $qq->whereRaw('LOWER(company_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(industry) LIKE ?', [$like])
                ->orWhereRaw('LOWER(primary_contact_email) LIKE ?', [$like])
                ->orWhereRaw('LOWER(primary_contact_phone) LIKE ?', [$like]);
        });
    }

    protected static function newFactory(): \Database\Factories\ClientFactory
    {
        return \Database\Factories\ClientFactory::new();
    }
}
