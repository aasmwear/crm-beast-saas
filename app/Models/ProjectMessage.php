<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProjectMessage extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProjectMessageFactory>
     *
     * @phpstan-use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProjectMessageFactory>
     */
    use HasFactory;

    protected $fillable = ['organization_id', 'project_id', 'author_id', 'user_id', 'parent_id', 'body', 'attachments'];

    protected $casts = ['attachments' => 'array'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Project, \App\Models\ProjectMessage>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\ProjectMessage>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ProjectMessage, \App\Models\ProjectMessage>
     *
     * @phpstan-return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ProjectMessage, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return \Database\Factories\ProjectMessageFactory
     */
    protected static function newFactory(): EloquentFactory
    {
        return \Database\Factories\ProjectMessageFactory::new();
    }
}
