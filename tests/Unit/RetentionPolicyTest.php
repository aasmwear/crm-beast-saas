<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RetentionPolicy;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class RetentionPolicyTest extends TestCase
{
    public function test_from_config_builds_correct_policy(): void
    {
        $policy = RetentionPolicy::fromConfig('audit_logs', [
            'category' => 'warm',
            'retention_days' => 90,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => 'Audit trail',
        ]);

        $this->assertSame('audit_logs', $policy->table);
        $this->assertSame('warm', $policy->category);
        $this->assertSame(90, $policy->retentionDays);
        $this->assertSame('created_at', $policy->createdAtColumn);
        $this->assertTrue($policy->orgScoped);
        $this->assertSame('Audit trail', $policy->description);
    }

    public function test_cutoff_date_calculation(): void
    {
        $policy = RetentionPolicy::fromConfig('test', [
            'category' => 'cold',
            'retention_days' => 30,
            'created_at_column' => 'created_at',
            'org_scoped' => false,
            'description' => 'Test',
        ]);

        $now = CarbonImmutable::parse('2026-03-18 12:00:00');
        $cutoff = $policy->cutoffDate($now);

        $this->assertSame('2026-02-16', $cutoff->toDateString());
    }

    public function test_category_helpers(): void
    {
        $hot = RetentionPolicy::fromConfig('t', [
            'category' => 'hot',
            'retention_days' => 365,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => '',
        ]);
        $this->assertTrue($hot->isHot());
        $this->assertFalse($hot->isWarm());
        $this->assertFalse($hot->isCold());
        $this->assertFalse($hot->hasLifecycleAction());

        $warm = RetentionPolicy::fromConfig('t', [
            'category' => 'warm',
            'retention_days' => 90,
            'created_at_column' => 'created_at',
            'org_scoped' => true,
            'description' => '',
        ]);
        $this->assertFalse($warm->isHot());
        $this->assertTrue($warm->isWarm());
        $this->assertFalse($warm->isCold());
        $this->assertTrue($warm->hasLifecycleAction());

        $cold = RetentionPolicy::fromConfig('t', [
            'category' => 'cold',
            'retention_days' => 30,
            'created_at_column' => 'created_at',
            'org_scoped' => false,
            'description' => '',
        ]);
        $this->assertFalse($cold->isHot());
        $this->assertFalse($cold->isWarm());
        $this->assertTrue($cold->isCold());
        $this->assertTrue($cold->hasLifecycleAction());
    }

    public function test_all_loads_from_config(): void
    {
        $policies = RetentionPolicy::all();

        $this->assertArrayHasKey('audit_logs', $policies);
        $this->assertArrayHasKey('activities', $policies);
        $this->assertArrayHasKey('stripe_webhook_events', $policies);
        $this->assertArrayHasKey('failed_jobs', $policies);

        $this->assertInstanceOf(RetentionPolicy::class, $policies['audit_logs']);
        $this->assertSame('warm', $policies['audit_logs']->category);
        $this->assertSame(90, $policies['audit_logs']->retentionDays);

        $this->assertSame('cold', $policies['failed_jobs']->category);
        $this->assertSame(30, $policies['failed_jobs']->retentionDays);
        $this->assertSame('failed_at', $policies['failed_jobs']->createdAtColumn);
        $this->assertFalse($policies['failed_jobs']->orgScoped);
    }

    public function test_from_config_defaults(): void
    {
        $policy = RetentionPolicy::fromConfig('unknown', []);

        $this->assertSame('hot', $policy->category);
        $this->assertSame(365, $policy->retentionDays);
        $this->assertSame('created_at', $policy->createdAtColumn);
        $this->assertTrue($policy->orgScoped);
        $this->assertSame('', $policy->description);
    }
}
