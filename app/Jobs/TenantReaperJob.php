<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\NotificationEvent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TenantReaperJob.
 *
 * Permanently deletes an organization and all associated data.
 * Used for churned tenants after grace period expires.
 *
 * DANGER: This is a destructive operation that cannot be undone.
 */
final class TenantReaperJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param  int  $organizationId  Organization ID to delete
     * @param  string  $reason  Reason for deletion (for logging)
     */
    public function __construct(
        public int $organizationId,
        public string $reason = 'Churned - Grace period expired',
    ) {
    }

    /**
     * Execute the job.
     *
     * Deletes all data associated with the organization in the correct order
     * to respect foreign key constraints.
     */
    public function handle(): void
    {
        $org = Organization::withTrashed()->find($this->organizationId);

        if (! $org) {
            Log::warning("TenantReaper: Organization {$this->organizationId} not found");

            return;
        }

        Log::info("TenantReaper: Starting deletion for Organization {$org->id} ({$org->name}). Reason: {$this->reason}");

        DB::transaction(function () use ($org): void {
            // ========================================
            // DELETE ORDER (RESPECT FOREIGN KEYS)
            // ========================================

            // 1. Audit Logs
            $this->deleteInChunks(
                AuditLog::where('organization_id', $org->id),
                'Audit Logs',
            );

            // 2. Notification Events
            $this->deleteInChunks(
                NotificationEvent::where('organization_id', $org->id),
                'Notification Events',
            );

            // 3. Project Messages
            $this->deleteInChunks(
                ProjectMessage::where('organization_id', $org->id),
                'Project Messages',
            );

            // 4. Announcements
            $this->deleteInChunks(
                Announcement::where('organization_id', $org->id),
                'Announcements',
            );

            // 5. Tasks (child of Projects)
            $this->deleteInChunks(
                Task::where('organization_id', $org->id),
                'Tasks',
            );

            // 6. Attendance Records
            $this->deleteInChunks(
                Attendance::where('organization_id', $org->id),
                'Attendance Records',
            );

            // 7. Projects (child of Clients)
            $this->deleteInChunks(
                Project::where('organization_id', $org->id),
                'Projects',
            );

            // 8. Clients
            $this->deleteInChunks(
                Client::where('organization_id', $org->id),
                'Clients',
            );

            // 9. Departments
            $this->deleteInChunks(
                Department::where('organization_id', $org->id),
                'Departments',
            );

            // 10. Detach users from organization (pivot table)
            DB::table('organization_user')
                ->where('organization_id', $org->id)
                ->delete();

            Log::info("TenantReaper: Detached all users from Organization {$org->id}");

            // 11. Clear active_organization_id from users
            User::where('active_organization_id', $org->id)
                ->update(['active_organization_id' => null]);

            Log::info("TenantReaper: Cleared active_organization_id for Organization {$org->id} users");

            // 12. Delete organization domains
            DB::table('organization_domains')
                ->where('organization_id', $org->id)
                ->delete();

            Log::info("TenantReaper: Deleted domains for Organization {$org->id}");

            // 13. Finally, force delete the organization itself
            $org->forceDelete();

            Log::info("TenantReaper: ✅ Successfully deleted Organization {$org->id} ({$org->name})");
        });
    }

    /**
     * Delete records in chunks to avoid memory issues.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Query builder
     * @param  string  $entityName  Name for logging
     */
    private function deleteInChunks($query, string $entityName): void
    {
        $count = $query->count();

        if ($count === 0) {
            return;
        }

        Log::info("TenantReaper: Deleting {$count} {$entityName}...");

        // Force delete in chunks of 100
        $query->chunkById(100, function ($records) {
            foreach ($records as $record) {
                $record->forceDelete();
            }
        });

        Log::info("TenantReaper: ✓ Deleted {$count} {$entityName}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("TenantReaper: FAILED to delete Organization {$this->organizationId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Send alert to super admins about failed deletion
    }
}
