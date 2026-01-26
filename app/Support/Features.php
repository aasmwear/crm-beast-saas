<?php

namespace App\Support;

use App\Models\Organization;

class Features
{
    public static function enabled(string $key, ?Organization $org = null): bool
    {
        // Future: check org-specific settings in DB
        return (bool) config("features.$key", false);
    }
}
