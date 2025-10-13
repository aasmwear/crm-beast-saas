<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    // If not already present, bind orgs by slug instead of id
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ✅ Needed for scoped bindings: /org/{organization}/clients/{client}
    public function clients(): HasMany
    {
        return $this->hasMany(\App\Models\Client::class);
    }

    // (Optional, but handy elsewhere)
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
