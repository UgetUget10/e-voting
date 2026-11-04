<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ballot;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BallotPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ballot');
    }

    public function view(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('View:Ballot');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ballot');
    }

    public function update(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('Update:Ballot');
    }

    public function delete(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('Delete:Ballot');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ballot');
    }

    public function restore(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('Restore:Ballot');
    }

    public function forceDelete(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('ForceDelete:Ballot');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Ballot');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Ballot');
    }

    public function replicate(AuthUser $authUser, Ballot $ballot): bool
    {
        return $authUser->can('Replicate:Ballot');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Ballot');
    }
}
