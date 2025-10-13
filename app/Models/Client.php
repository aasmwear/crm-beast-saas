<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Organization;
use App\Models\User;

class Client extends Model
{
    protected $fillable = [
        'organization_id',
        'company_name','industry','niche',
        'primary_contact_name','primary_contact_email','primary_contact_phone',
        'website','address','tags',
        'fronter','closer',
        'assigned_account_manager_id',
        'google_business_profile_status',
        'google_business_profile_access_status',
        'client_activation_status',
        'notes_by_cst','notes_by_sales','notes_by_tech',
        'status',
        // legacy fields kept compatible; harmless if present
        'name','business_name','email','phone_1','phone_2','price','gmb_status','gmb_access_status','project_activation_status',
    ];

    protected $casts = [
        'fronter' => 'array',
        'closer'  => 'array',
        'tags'    => 'array',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function accountManager() { return $this->belongsTo(User::class, 'assigned_account_manager_id'); }
    public function projects() { return $this->hasMany(Project::class); }

    // Tenant filter
    public function scopeForOrg(Builder $q, Organization $org): Builder
    {   return $q->where('organization_id', $org->id); }

    // Dashboard visibility rule (spec): closer/fronter/AM/PM/assignee
    public function scopeVisibleTo(Builder $q, User $user, Organization $org): Builder
    {
        // Admins & DepartmentHeads in this org see all clients
        if ($user->hasRole(['Admin','DepartmentHead'], $org)) {
            return $q->forOrg($org);
        }

        return $q->forOrg($org)->where(function ($qq) use ($user) {
            $qq->whereJsonContains('fronter', $user->id)
               ->orWhereJsonContains('closer', $user->id)
               ->orWhere('assigned_account_manager_id', $user->id)
               ->orWhereExists(function ($pm) use ($user) {
                   $pm->from('projects')
                      ->whereColumn('projects.client_id', 'clients.id')
                      ->where('projects.project_manager_id', $user->id);
               })
               ->orWhereExists(function ($assn) use ($user) {
                   $assn->from('projects')
                        ->whereColumn('projects.client_id', 'clients.id')
                        ->whereExists(function ($tq) use ($user) {
                            $tq->from('tasks')
                               ->whereColumn('tasks.project_id', 'projects.id')
                               ->whereJsonContains('assignees', $user->id);
                        });
               });
        });
    }

    // Search helper
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.strtolower($term).'%';
        return $q->where(function ($qq) use ($like) {
            $qq->whereRaw('LOWER(company_name) LIKE ?', [$like])
               ->orWhereRaw('LOWER(primary_contact_email) LIKE ?', [$like])
               ->orWhereRaw('LOWER(primary_contact_name) LIKE ?', [$like])
               ->orWhereRaw('LOWER(primary_contact_phone) LIKE ?', [$like]);
        });
    }
}
