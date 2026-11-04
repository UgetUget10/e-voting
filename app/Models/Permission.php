<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Spatie's permission model with a UUID primary key, so permissions share the same key type as every other table.
 */
class Permission extends SpatiePermission
{
    use HasUuids;
}
