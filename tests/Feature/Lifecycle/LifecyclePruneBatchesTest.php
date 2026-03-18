<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LifecyclePruneBatchesTest extends TestCase
{
    use RefreshDatabase;

    private function insertAgedFinishedBatch(int $daysAgo): void
    {
        $finishedAt = now()->subDays($daysAgo)->timestamp;
        $createdAt = now()->subDays($daysAgo + 1)->timestamp;

        DB::table('job_batches')->insert([
            'id' => Str::uuid()->toString(),
            'name' => 'test-batch',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'created_at' => $createdAt,
            'finished_at' => $finishedAt,
        ]);
    }

    public function test_prune_batches_dry_run_does_not_delete(): void
    {
        $this->insertAgedFinishedBatch(40);

        $countBefore = DB::table('job_batches')->count();
        $this->assertSame(1, $countBefore);

        $this->artisan('lifecycle:prune-batches')
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows (finished batches): 1')
            ->expectsOutputToContain('[DRY-RUN]');

        $countAfter = DB::table('job_batches')->count();
        $this->assertSame(1, $countAfter, 'Dry-run must not delete any rows');
    }

    public function test_prune_batches_execute_deletes_aged_out(): void
    {
        $this->insertAgedFinishedBatch(40);

        $this->artisan('lifecycle:prune-batches', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('job_batches', 0);
    }

    public function test_prune_batches_output_is_clear(): void
    {
        $this->artisan('lifecycle:prune-batches')
            ->assertSuccessful()
            ->expectsOutputToContain('Lifecycle Prune: job_batches')
            ->expectsOutputToContain('Cutoff date:');
    }
}
