<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Attendance
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $clock_in_at
 * @property string|null $clock_in_ip
 * @property string|null $clock_in_geo
 * @property \Illuminate\Support\Carbon|null $clock_out_at
 * @property string|null $clock_out_ip
 * @property string|null $clock_out_geo
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $minutes
 * @property string|null $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class Attendance extends Model
{
    use HasFactory; // @phpstan-ignore-line
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * NOTE: Migration uses singular "attendance".
     */
    protected $table = 'attendance';

    /**
     * The attributes that are mass assignable.
     *
     * (No PHPDoc here so we don't override the parent Model::$fillable type.)
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'clock_in_at',
        'clock_in_ip',
        'clock_in_geo',
        'clock_out_at',
        'clock_out_ip',
        'clock_out_geo',
        'approved_by',
        'approved_at',
        'minutes',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
        'minutes' => 'integer',
    ];

    /**
     * Get the organization this attendance belongs to.
     */
    // @phpstan-ignore-next-line
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user this attendance record belongs to.
     */
    // @phpstan-ignore-next-line
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
