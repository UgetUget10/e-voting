<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie's role model with a UUID primary key, so roles share the same key type as every other table.
 */
class Role extends SpatieRole
{
    use HasUuids;
}
