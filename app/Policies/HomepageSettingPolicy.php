<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HomepageSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class HomepageSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HomepageSetting');
    }

    public function view(AuthUser $authUser, HomepageSetting $homepageSetting): bool
    {
        return $authUser->can('View:HomepageSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HomepageSetting');
    }

    public function update(AuthUser $authUser, HomepageSetting $homepageSetting): bool
    {
        return $authUser->can('Update:HomepageSetting');
    }

    public function delete(AuthUser $authUser, HomepageSetting $homepageSetting): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
