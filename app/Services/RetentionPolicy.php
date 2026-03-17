<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Read-only value object for a table's retention policy.
 *
 * Constructed from config/lifecycle.php entries. Provides helpers
 * to compute cutoff dates and check if a table has aged-out rows.
 */
final class RetentionPolicy
{
    public function __construct(
        public readonly string $table,
        public readonly string $category,
        public readonly int $retentionDays,
        public readonly string $createdAtColumn,
        public readonly bool $orgScoped,
        public readonly string $description,
    ) {}

    /**
     * Build a RetentionPolicy from a config/lifecycle.php entry.
     *
     * @param  array{category: string, retention_days: int, created_at_column: string, org_scoped: bool, description: string}  $config
     */
    public static function fromConfig(string $table, array $config): self
    {
        return new self(
            table: $table,
            category: $config['category'] ?? 'hot',
            retentionDays: (int) ($config['retention_days'] ?? 365),
            createdAtColumn: $config['created_at_column'] ?? 'created_at',
            orgScoped: (bool) ($config['org_scoped'] ?? true),
            description: $config['description'] ?? '',
        );
    }

    /**
     * Load all retention policies from config.
     *
     * @return array<string, RetentionPolicy>
     */
    public static function all(): array
    {
        /** @var array<string, array{category: string, retention_days: int, created_at_column: string, org_scoped: bool, description: string}> $tables */
        $tables = config('lifecycle.tables', []);

        $policies = [];
        foreach ($tables as $table => $config) {
            $policies[$table] = self::fromConfig($table, $config);
        }

        return $policies;
    }

    /**
     * The cutoff date: rows older than this are outside the retention window.
     */
    public function cutoffDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        return $now->subDays($this->retentionDays);
    }

    /**
     * Whether this is a warm-category table (archive candidate).
     */
    public function isWarm(): bool
    {
        return $this->category === 'warm';
    }

    /**
     * Whether this is a cold-category table (prune candidate).
     */
    public function isCold(): bool
    {
        return $this->category === 'cold';
    }

    /**
     * Whether this is a hot-category table (keep indefinitely).
     */
    public function isHot(): bool
    {
        return $this->category === 'hot';
    }

    /**
     * Whether this policy covers archival (warm) or pruning (cold) candidates.
     */
    public function hasLifecycleAction(): bool
    {
        return $this->isWarm() || $this->isCold();
    }
}
