<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

final class Setting extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\SettingFactory>
     *
     * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\SettingFactory>
     */
    use HasFactory;

    protected $fillable = ['organization_id', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function get(int $organizationId, string $key, mixed $default = null): mixed
    {
        $row = self::query()
            ->where('organization_id', $organizationId)
            ->where('key', $key)
            ->first();

        if (! $row) {
            return $default;
        }
        $val = $row->value;

        return is_array($val) && array_key_exists('value', $val) ? $val['value'] : $val;
    }

    public static function put(int $organizationId, string $key, mixed $value): void
    {
        $payload = is_array($value) ? $value : ['value' => $value];

        self::updateOrCreate(
            ['organization_id' => $organizationId, 'key' => $key],
            ['value' => $payload]
        );
    }

    /** Keys that are stored encrypted at rest. */
    private const ENCRYPTED_KEYS = ['slack_webhook_url', 'smtp_pass'];

    public static function putEncrypted(int $organizationId, string $key, string $value): void
    {
        if (! in_array($key, self::ENCRYPTED_KEYS, true)) {
            throw new \InvalidArgumentException("Key {$key} is not an encrypted setting key.");
        }
        $encrypted = Crypt::encryptString($value);
        self::put($organizationId, $key, $encrypted);
    }

    /**
     * Get decrypted value for server-side use only. Never expose to frontend.
     */
    public static function getDecrypted(int $organizationId, string $key): ?string
    {
        $raw = self::get($organizationId, $key);
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! in_array($key, self::ENCRYPTED_KEYS, true)) {
            return is_string($raw) ? $raw : null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return is_string($raw) ? $raw : null;
        }
    }

    public static function isSecretSet(int $organizationId, string $key): bool
    {
        $raw = self::get($organizationId, $key);

        return $raw !== null && $raw !== '';
    }

    /**
     * @return \Database\Factories\SettingFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\SettingFactory::new();
    }
}
