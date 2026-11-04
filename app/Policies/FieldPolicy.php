<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Field;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FieldPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Field');
    }

    public function view(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('View:Field');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Field');
    }

    public function update(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('Update:Field');
    }

    public function delete(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('Delete:Field');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Field');
    }

    public function restore(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('Restore:Field');
    }

    public function forceDelete(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('ForceDelete:Field');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Field');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Field');
    }

    public function replicate(AuthUser $authUser, Field $field): bool
    {
        return $authUser->can('Replicate:Field');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Field');
    }
}
