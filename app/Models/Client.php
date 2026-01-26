<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Client model.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $company_name
 * @property string|null $industry
 * @property string|null $niche
 * @property string|null $primary_contact_name
 * @property string|null $primary_contact_email
 * @property string|null $primary_contact_phone
 * @property string|null $website
 * @property string|null $address
 * @property array<int, int>|null $fronter
 * @property array<int, int>|null $closer
 * @property array<int, string>|null $tags
 * @property int|null $assigned_account_manager_id
 * @property string|null $google_business_profile_status
 * @property string|null $google_business_profile_access_status
 * @property string|null $client_activation_status
 * @property string|null $notes_by_cst
 * @property string|null $notes_by_sales
 * @property string|null $notes_by_tech
 * @property string|null $status
 *
 * @method static Builder<Client> query()
 * @method static ClientFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
final class Client extends Model
{
    /**
     * @use HasFactory<ClientFactory>
     */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
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
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'fronter' => 'array',
        'closer' => 'array',
    ];

    /**
     * @return BelongsTo<Organization, Client>
     *
     * @phpstan-return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Project, Client>
     *
     * @phpstan-return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Account manager relation.
     *
     * @return BelongsTo<User, Client>
     *
     * @phpstan-return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_account_manager_id');
    }

    /**
     * Scope by organization (multi-tenant helper).
     *
     * @param  Builder<Client>  $q
     * @return Builder<Client>
     */
    public function scopeForOrg(Builder $q, int $orgId): Builder
    {
        return $q->where('organization_id', $orgId);
    }

    /**
     * Visibility rule for clients.
     *
     * - Super Admins, Admins and Owners can see **all** clients in the organization.
     * - Everyone else only sees clients where:
     *   - their id appears in `fronter` JSON, or
     *   - their id appears in `closer` JSON, or
     *   - `assigned_account_manager_id` = their id, or
     *   - they are `project_manager_id` on at least one related project.
     *   - they are assigned to at least one task on any related project.
     *
     * @param  Builder<Client>  $q
     * @return Builder<Client>
     */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ((bool) ($user->is_super_admin ?? false) || $user->hasAnyRole(['Owner', 'Admin'])) {
            return $q;
        }

        $uid = $user->id;

        return $q->where(function (Builder $qq) use ($uid): void {
            $qq->whereJsonContains('fronter', $uid)
                ->orWhereJsonContains('closer', $uid)
                ->orWhere('assigned_account_manager_id', $uid)
                ->orWhereHas('projects', static function (Builder $qp) use ($uid): void {
                    $qp->where('project_manager_id', $uid);
                })
                ->orWhereHas('projects.tasks', static function (Builder $qt) use ($uid): void {
                    $qt->whereJsonContains('assignees', $uid);
                });
        });
    }
}
