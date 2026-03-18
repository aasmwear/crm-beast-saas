<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LifecyclePruneFailedTest extends TestCase
{
    use RefreshDatabase;

    private function insertAgedFailedJob(int $daysAgo): void
    {
        $failedAt = now()->subDays($daysAgo);

        DB::table('failed_jobs')->insert([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['job' => 'test', 'data' => []]),
            'exception' => 'Test exception',
            'failed_at' => $failedAt,
        ]);
    }

    public function test_prune_failed_dry_run_does_not_delete(): void
    {
        $this->insertAgedFailedJob(40);

        $countBefore = DB::table('failed_jobs')->count();
        $this->assertSame(1, $countBefore);

        $this->artisan('lifecycle:prune-failed')
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows: 1')
            ->expectsOutputToContain('[DRY-RUN]');

        $countAfter = DB::table('failed_jobs')->count();
        $this->assertSame(1, $countAfter, 'Dry-run must not delete any rows');
    }

    public function test_prune_failed_execute_deletes_aged_out(): void
    {
        $this->insertAgedFailedJob(40);

        $this->artisan('lifecycle:prune-failed', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_prune_failed_execute_preserves_recent(): void
    {
        $this->insertAgedFailedJob(10); // Within 30-day window

        $this->artisan('lifecycle:prune-failed', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No rows to prune');

        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_prune_failed_output_is_clear(): void
    {
        $this->artisan('lifecycle:prune-failed')
            ->assertSuccessful()
            ->expectsOutputToContain('Lifecycle Prune: failed_jobs')
            ->expectsOutputToContain('Cutoff date:')
            ->expectsOutputToContain('Candidate rows:');
    }
}
