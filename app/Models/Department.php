<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Department extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\DepartmentFactory>
     *
     * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\DepartmentFactory>
     */
    use HasFactory;

    // Match the actual table schema: no 'description' column
    protected $fillable = ['organization_id', 'name', 'code'];

    /**
     * @return \Database\Factories\DepartmentFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\DepartmentFactory::new();
    }
}
