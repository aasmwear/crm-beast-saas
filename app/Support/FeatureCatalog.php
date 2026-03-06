<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side feature catalog for tenant modules/feature flags.
 * Used for validation and UI rendering; avoid hardcoding in Vue.
 *
 * @return array<int, array{key: string, label: string, description: string, type: string, default: bool|int}>
 */
final class FeatureCatalog
{
    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_NUMBER = 'number';

    /**
     * @return array<int, array{key: string, label: string, description: string, type: string, default: bool|int}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'attendance',
                'label' => 'Attendance',
                'description' => 'Enable clock-in/clock-out and attendance tracking.',
                'type' => self::TYPE_BOOLEAN,
                'default' => true,
            ],
            [
                'key' => 'sms',
                'label' => 'SMS Notifications',
                'description' => 'Enable SMS notifications for alerts and reminders.',
                'type' => self::TYPE_BOOLEAN,
                'default' => false,
            ],
            [
                'key' => 'api_access',
                'label' => 'API Access',
                'description' => 'Enable API access for integrations and external tools.',
                'type' => self::TYPE_BOOLEAN,
                'default' => false,
            ],
            [
                'key' => 'storage_gb',
                'label' => 'Storage (GB)',
                'description' => 'Storage quota in gigabytes for file uploads.',
                'type' => self::TYPE_NUMBER,
                'default' => 5,
            ],
            [
                'key' => 'api_rpm',
                'label' => 'API requests per minute',
                'description' => 'Rate limit for API requests per minute.',
                'type' => self::TYPE_NUMBER,
                'default' => 60,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::all() as $item) {
            $keys[$item['key']] = $item['key'];
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::all() as $item) {
            $defaults[$item['key']] = $item['default'];
        }

        return $defaults;
    }

    public static function isValidKey(string $key): bool
    {
        return array_key_exists($key, self::keys());
    }

    public static function getType(string $key): ?string
    {
        foreach (self::all() as $item) {
            if ($item['key'] === $key) {
                return $item['type'];
            }
        }

        return null;
    }
}
