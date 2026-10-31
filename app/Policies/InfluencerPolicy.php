<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Influencer;
use Illuminate\Auth\Access\HandlesAuthorization;

class InfluencerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Influencer');
    }

    public function view(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('View:Influencer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Influencer');
    }

    public function update(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('Update:Influencer');
    }

    public function delete(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('Delete:Influencer');
    }

    public function restore(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('Restore:Influencer');
    }

    public function forceDelete(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('ForceDelete:Influencer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Influencer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Influencer');
    }

    public function replicate(AuthUser $authUser, Influencer $influencer): bool
    {
        return $authUser->can('Replicate:Influencer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Influencer');
    }

}