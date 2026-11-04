<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Election;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ElectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Election');
    }

    public function view(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('View:Election');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Election');
    }

    public function update(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('Update:Election');
    }

    public function delete(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('Delete:Election');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Election');
    }

    public function restore(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('Restore:Election');
    }

    public function forceDelete(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('ForceDelete:Election');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Election');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Election');
    }

    public function replicate(AuthUser $authUser, Election $election): bool
    {
        return $authUser->can('Replicate:Election');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Election');
    }
}
