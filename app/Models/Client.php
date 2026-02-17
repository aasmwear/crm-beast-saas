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
 * @property int|null $fronter_id
 * @property int|null $closer_id
 * @property array<int, string>|null $tags
 * @property int|null $assigned_account_manager_id
 * @property string|null $gbp_status
 * @property string|null $gbp_access
 * @property string|null $client_activation_status
 * @property string|null $notes_cst
 * @property string|null $notes_sales
 * @property string|null $notes_tech
 * @property string|null $status
 * @property string|null $tax_id
 * @property string $currency
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
        'tax_id',
        'currency',
        'tags',
        'fronter_id',
        'closer_id',
        'assigned_account_manager_id',
        'gbp_status',
        'gbp_access',
        'client_activation_status',
        'notes_cst',
        'notes_sales',
        'notes_tech',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
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
     * @return HasMany<ClientContact, Client>
     *
     * @phpstan-return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * @return HasMany<Invoice, Client>
     *
     * @phpstan-return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Fronter (sales lead) relation - single user responsible for initial contact.
     *
     * @return BelongsTo<User, Client>
     *
     * @phpstan-return BelongsTo<User, $this>
     */
    public function fronter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fronter_id');
    }

    /**
     * Closer (sales closer) relation - single user responsible for closing the deal.
     *
     * @return BelongsTo<User, Client>
     *
     * @phpstan-return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_id');
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
     *   - `fronter_id` = their id, or
     *   - `closer_id` = their id, or
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
            $qq->where('fronter_id', $uid)
                ->orWhere('closer_id', $uid)
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
