<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'company_name',
        'business_name',
        'email',
        'phone_1',
        'phone_2',
        'price',
        'gmb_status',
        'project_activation_status',
        'activation_date',
        'account_manager_id',
        'fronter',
        'closer',
        'niche',
        'services_notes',
        'yelp_link',
        'project_scope',
        'business_address',
        'business_address_status',
        'cst_notes',
        'special_notes',
        'gmb_access_status',
        'status',
        'search_vector',
    ];

    protected $casts = [
        'project_scope' => 'array',
        'activation_date' => 'date',
        'fronter' => 'array',
        'closer'  => 'array',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'account_manager_id'); }
    public function projects() { return $this->hasMany(Project::class); }
    public function tasks() { return $this->hasManyThrough(Task::class, Project::class); }

    /** Tenant filter */
    public function scopeForOrg(Builder $q, Organization $org): Builder
    {
        return $q->where('organization_id', $org->id);
    }

    /** Visibility rule:
     * - Admin/HR/DepartmentHead: all clients in org
     * - Others: client.account_manager_id = me OR user has tasks on any project for this client
     */
    public function scopeVisibleTo(Builder $q, User $user, Organization $org): Builder
    {
        if ($user->hasRole(['Admin','HR','DepartmentHead'], $org)) {
            return $q->forOrg($org);
        }

        return $q->forOrg($org)->where(function ($qq) use ($user) {
            $qq->where('account_manager_id', $user->id)
               ->orWhereHas('projects.tasks', function ($tq) use ($user) {
                   $tq->whereJsonContains('assignees', $user->id);
               });
        });
    }

    /** Basic search across popular fields */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.strtolower($term).'%';
        return $q->where(function ($qq) use ($like) {
            $qq->whereRaw('LOWER(company_name) LIKE ?', [$like])
               ->orWhereRaw('LOWER(business_name) LIKE ?', [$like])
               ->orWhereRaw('LOWER(email) LIKE ?', [$like])
               ->orWhereRaw('LOWER(phone_1) LIKE ?', [$like]);
        });
    }
}
