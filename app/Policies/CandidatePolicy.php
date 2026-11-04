<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Candidate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CandidatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Candidate');
    }

    public function view(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('View:Candidate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Candidate');
    }

    public function update(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('Update:Candidate');
    }

    public function delete(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('Delete:Candidate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Candidate');
    }

    public function restore(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('Restore:Candidate');
    }

    public function forceDelete(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('ForceDelete:Candidate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Candidate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Candidate');
    }

    public function replicate(AuthUser $authUser, Candidate $candidate): bool
    {
        return $authUser->can('Replicate:Candidate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Candidate');
    }
}
