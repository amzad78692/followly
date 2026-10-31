<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Taxonomy;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaxonomyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Taxonomy');
    }

    public function view(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('View:Taxonomy');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Taxonomy');
    }

    public function update(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('Update:Taxonomy');
    }

    public function delete(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('Delete:Taxonomy');
    }

    public function restore(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('Restore:Taxonomy');
    }

    public function forceDelete(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('ForceDelete:Taxonomy');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Taxonomy');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Taxonomy');
    }

    public function replicate(AuthUser $authUser, Taxonomy $taxonomy): bool
    {
        return $authUser->can('Replicate:Taxonomy');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Taxonomy');
    }

}