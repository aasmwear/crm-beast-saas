<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Department model.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property bool $is_pm_capable
 *
 * @mixin \Eloquent
 */
final class Department extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\DepartmentFactory>
     *
     * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\DepartmentFactory>
     */
    use HasFactory;

    protected $fillable = ['organization_id', 'name', 'code', 'is_pm_capable'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_pm_capable' => 'boolean',
    ];

    /**
     * @return \Database\Factories\DepartmentFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\DepartmentFactory::new();
    }
}
