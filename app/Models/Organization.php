<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'settings',
    ];

    /**
     * Tell Laravel to use the slug in URLs/route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // --- Relationships used around the app ---
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // add others as you grow (tasks, users, etc.)
}
