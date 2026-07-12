<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Province;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProvincePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Province');
    }

    public function view(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('View:Province');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Province');
    }

    public function update(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('Update:Province');
    }

    public function delete(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('Delete:Province');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Province');
    }

    public function restore(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('Restore:Province');
    }

    public function forceDelete(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('ForceDelete:Province');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Province');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Province');
    }

    public function replicate(AuthUser $authUser, Province $province): bool
    {
        return $authUser->can('Replicate:Province');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Province');
    }
}
