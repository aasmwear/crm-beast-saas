<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'plan', 'settings'];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
