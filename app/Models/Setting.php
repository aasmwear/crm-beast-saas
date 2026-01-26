<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * @return \Database\Factories\SettingFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\SettingFactory::new();
    }
}
