<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Project file attachment.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property int $user_id
 * @property string $filename
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_visible_to_client
 */
final class ProjectFile extends Model
{
    protected $fillable = [
        'organization_id', 'project_id', 'user_id', 'filename', 'path', 'mime_type', 'size',
        'is_visible_to_client',
    ];

    protected $casts = [
        'is_visible_to_client' => 'boolean',
        'size' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
