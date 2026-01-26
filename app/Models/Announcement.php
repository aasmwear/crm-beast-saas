<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Announcement model.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $author_id
 * @property string $title
 * @property string|null $body
 * @property bool $pinned
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder<Announcement> query()
 * @method static AnnouncementFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Announcement extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\AnnouncementFactory>
     */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'author_id',
        'title',
        'body',
        'pinned',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'organization_id' => 'integer',
        'author_id' => 'integer',
        'pinned' => 'boolean',
    ];

    /**
     * Get the organization that owns this announcement.
     *
     * @return BelongsTo<\App\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Author (user) who created the announcement.
     *
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope: limit to a single organization.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AnnouncementFactory
    {
        return AnnouncementFactory::new();
    }
}
