<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * User can update project if they are owner, editor, or admin.
     */
    public function update(?User $user, Project $project): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $role = $project->roleFor($user);
        return $role === Project::ROLE_OWNER || $role === Project::ROLE_EDITOR;
    }

    /**
     * User can view project (same visibility as existing authorizeProject).
     */
    public function view(?User $user, Project $project): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if ($project->trashed()) {
            return false;
        }
        return $project->visibility === Project::VISIBILITY_PUBLIC
            || $project->owner_id === (int) $user->id
            || $project->accessUsers()->where('users.id', $user->id)->exists();
    }
}
