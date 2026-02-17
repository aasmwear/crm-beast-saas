<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// ========================================
// USER PRESENCE CHANNEL
// ========================================
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

// ========================================
// PROJECT CHANNELS (TENANT-SCOPED)
// ========================================

/**
 * Organization Project Channel.
 *
 * Channel: private-org.{orgId}.projects.{projectId}
 * Authorization: User must belong to the organization AND have access to the project.
 */
Broadcast::channel('org.{orgId}.projects.{projectId}', function (User $user, int $orgId, int $projectId) {
    // 1. Check if user belongs to the organization
    $organization = Organization::find($orgId);
    if (! $organization) {
        return false;
    }

    // Check if user is a member of this organization
    if (! $organization->users()->where('users.id', $user->id)->exists()) {
        return false;
    }

    // 2. Check if project exists and belongs to this organization
    $project = Project::where('id', $projectId)
        ->where('organization_id', $orgId)
        ->first();

    if (! $project) {
        return false;
    }

    // 3. Check if user has access to this project (using visibility scope)
    $hasAccess = Project::where('id', $projectId)
        ->where('organization_id', $orgId)
        ->visibleTo($user)
        ->exists();

    return $hasAccess;
});

// ========================================
// PLATFORM CHANNELS (SUPER ADMIN ONLY)
// ========================================

/**
 * Platform Announcements Channel.
 *
 * Channel: public-platform-announcements
 * Authorization: Only Super Admins can listen.
 */
Broadcast::channel('platform-announcements', function (User $user) {
    // Only super admins can listen to platform announcements
    return (bool) ($user->is_super_admin ?? false);
});
