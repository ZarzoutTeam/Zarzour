<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->can('View:User');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(User $authUser, User $user): bool
    {
        if (! $authUser->can('Update:User')) {
            return false;
        }

        return ! $user->hasRole('super-admin') || $authUser->hasRole('super-admin');
    }

    public function delete(User $authUser, User $user): bool
    {
        if (! $authUser->can('Delete:User') || $authUser->is($user)) {
            return false;
        }

        if (! $user->hasRole('super-admin')) {
            return true;
        }

        return $authUser->hasRole('super-admin')
            && User::role('super-admin')->whereKeyNot($user->getKey())->exists();
    }

    public function deleteAny(User $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(User $authUser, User $user): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, User $user): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}
