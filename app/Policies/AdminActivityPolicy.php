<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminActivity;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AdminActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdminActivity');
    }

    public function view(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('View:AdminActivity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdminActivity');
    }

    public function update(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('Update:AdminActivity');
    }

    public function delete(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('Delete:AdminActivity');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdminActivity');
    }

    public function restore(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('Restore:AdminActivity');
    }

    public function forceDelete(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('ForceDelete:AdminActivity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdminActivity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdminActivity');
    }

    public function replicate(AuthUser $authUser, AdminActivity $adminActivity): bool
    {
        return $authUser->can('Replicate:AdminActivity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdminActivity');
    }
}
