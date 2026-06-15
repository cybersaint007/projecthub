<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $projects = Project::withTrashed()
                ->with(['owner', 'accessUsers'])
                ->withCount(['epics', 'accessUsers'])
                ->orderBy('deleted_at', 'asc')
                ->orderBy('name', 'asc')
                ->get();
        } else {
            $projects = Project::accessibleTo($user)
                ->with(['owner', 'accessUsers'])
                ->withCount('epics')
                ->latest()
                ->get();
        }

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Project::create($data);

        return redirect()->route('projects.index')->with('status', __('ui.flash_project_created'));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        // Allow viewing soft-deleted projects for admins
        if ($user->isAdmin()) {
            $project = Project::withTrashed()->findOrFail($id);
        } else {
            $project = Project::findOrFail($id);
        }

        $this->authorizeProject($user, $project);

        // Load epics and tasks (including trashed for admins)
        if ($user->isAdmin()) {
            $project->load(['epics' => function ($query) {
                $query->withTrashed();
            }, 'epics.tasks' => function ($query) {
                $query->withTrashed();
            }, 'owner', 'accessUsers']);
        } else {
            $project->load(['epics.tasks', 'owner', 'accessUsers']);
        }

        return view('projects.show', compact('project'));
    }

    public function access(Request $request, Project $project)
    {
        $user = $request->user();
        $this->authorizeProject($user, $project);
        $role = $project->roleFor($user);
        if ($role !== Project::ROLE_OWNER && !$user->isAdmin()) {
            abort(403, __('ui.error_only_owner_manage_access'));
        }
        $project->load(['owner', 'accessUsers']);
        $userIdsOnProject = $project->accessUsers->pluck('id')->when($project->owner_id, fn ($ids) => $ids->push($project->owner_id))->unique()->values();
        $availableUsers = User::orderBy('name')->get()->filter(fn ($u) => !$userIdsOnProject->contains($u->id))->values();
        $allUsers = User::orderBy('name')->get();
        return view('projects.access', compact('project', 'availableUsers', 'allUsers'));
    }

    public function addAccessUser(Request $request, Project $project)
    {
        $user = $request->user();
        $this->authorizeProject($user, $project);
        if ($project->roleFor($user) !== Project::ROLE_OWNER && !$user->isAdmin()) {
            abort(403, __('ui.error_only_owner_manage_access'));
        }
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:editor,viewer',
        ]);
        if ($project->owner_id && (int) $project->owner_id === (int) $data['user_id']) {
            return redirect()->route('projects.access', $project)->with('error', __('ui.error_user_already_owner'));
        }
        $project->accessUsers()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role']]]);
        return redirect()->route('projects.access', $project)->with('status', __('ui.flash_user_added'));
    }

    public function updateAccessUser(Request $request, Project $project, User $user)
    {
        $authUser = $request->user();
        $this->authorizeProject($authUser, $project);
        if ($project->roleFor($authUser) !== Project::ROLE_OWNER && !$authUser->isAdmin()) {
            abort(403, __('ui.error_only_owner_manage_access'));
        }
        if ($project->owner_id && (int) $project->owner_id === (int) $user->id) {
            return redirect()->route('projects.access', $project)->with('error', __('ui.error_change_owner_first'));
        }
        $data = $request->validate(['role' => 'required|in:editor,viewer']);
        $project->accessUsers()->updateExistingPivot($user->id, ['role' => $data['role']]);
        return redirect()->route('projects.access', $project)->with('status', __('ui.flash_role_updated'));
    }

    public function removeAccessUser(Request $request, Project $project, User $user)
    {
        $authUser = $request->user();
        $this->authorizeProject($authUser, $project);
        if ($project->roleFor($authUser) !== Project::ROLE_OWNER && !$authUser->isAdmin()) {
            abort(403, __('ui.error_only_owner_manage_access'));
        }
        if ($project->owner_id && (int) $project->owner_id === (int) $user->id) {
            return redirect()->route('projects.access', $project)->with('error', __('ui.error_set_owner_before_remove'));
        }
        $project->accessUsers()->detach($user->id);
        return redirect()->route('projects.access', $project)->with('status', __('ui.flash_user_removed'));
    }

    public function updateOwner(Request $request, Project $project)
    {
        $authUser = $request->user();
        $this->authorizeProject($authUser, $project);
        if ($project->roleFor($authUser) !== Project::ROLE_OWNER && !$authUser->isAdmin()) {
            abort(403, __('ui.error_only_owner_manage_access'));
        }
        $data = $request->validate(['owner_id' => 'required|exists:users,id']);
        $project->update(['owner_id' => $data['owner_id']]);
        $project->accessUsers()->syncWithoutDetaching([$data['owner_id'] => ['role' => Project::ROLE_VIEWER]]);
        return redirect()->route('projects.access', $project)->with('status', __('ui.flash_owner_updated'));
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('status', __('ui.flash_project_updated'));
    }

    public function destroy(Request $request, Project $project)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($project) {
            // Soft delete all related epics
            $project->epics()->each(function ($epic) {
                // Soft delete all tasks under each epic
                $epic->tasks()->delete();
                // Soft delete the epic
                $epic->delete();
            });

            // Detach users from project (optional - keeping it simple, we don't reattach on restore)
            $project->users()->detach();

            // Soft delete the project
            $project->delete();

            return redirect()->route('projects.index')->with('status', __('ui.flash_project_deleted'));
        });
    }

    /**
     * Restore a soft-deleted project
     */
    public function restore(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            // Load the soft-deleted project by ID
            $project = Project::onlyTrashed()->findOrFail($id);

            // Restore all related epics
            $project->epics()->onlyTrashed()->each(function ($epic) {
                // Restore all tasks under each epic
                $epic->tasks()->onlyTrashed()->restore();
                // Restore the epic
                $epic->restore();
            });

            // Restore the project
            $project->restore();

            return redirect()->route('projects.show', $project)->with('status', __('ui.flash_project_restored'));
        });
    }

    /**
     * Reorder epics within a project. Used by both List and Kanban views.
     */
    public function reorderEpics(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'epic_ids' => 'required|array',
            'epic_ids.*' => 'integer',
        ]);

        $epicIds = array_values(array_unique(array_map('intval', $data['epic_ids'])));
        $epicIds = array_filter($epicIds, fn ($id) => $id > 0);
        if ($epicIds === []) {
            throw ValidationException::withMessages(['epic_ids' => ['At least one epic is required.']]);
        }

        $validIds = $project->epics()->withTrashed()->whereIn('id', $epicIds)->pluck('id')->all();
        $invalid = array_diff($epicIds, $validIds);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'epic_ids' => ['One or more epics do not belong to this project.'],
            ]);
        }

        DB::transaction(function () use ($epicIds) {
            foreach ($epicIds as $index => $id) {
                Epic::withTrashed()->where('id', $id)->update(['position' => ($index + 1) * 10]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Reorder tasks (and move across epics). Used by both List and Kanban views.
     */
    public function reorderTasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'columns' => 'required|array',
            'columns.*.epic_id' => 'required|integer',
            'columns.*.task_ids' => 'required|array',
            'columns.*.task_ids.*' => 'integer',
        ]);

        $projectEpicIds = $project->epics()->pluck('id')->all();
        $allTaskIds = [];

        foreach ($data['columns'] as $col) {
            $epicId = (int) $col['epic_id'];
            if (!in_array($epicId, $projectEpicIds, true)) {
                throw ValidationException::withMessages([
                    'columns' => ['One or more epic_ids do not belong to this project.'],
                ]);
            }
            $taskIds = array_values(array_unique(array_map('intval', $col['task_ids'])));
            foreach (array_filter($taskIds, fn ($id) => $id > 0) as $tid) {
                $allTaskIds[] = $tid;
            }
        }

        $allTaskIds = array_values(array_unique($allTaskIds));
        $projectTaskIds = Task::query()
            ->withTrashed()
            ->whereIn('id', $allTaskIds)
            ->whereHas('epic', fn ($q) => $q->where('project_id', $project->id))
            ->pluck('id')
            ->all();
        $invalid = array_diff($allTaskIds, $projectTaskIds);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'columns' => ['One or more task_ids do not belong to this project.'],
            ]);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['columns'] as $col) {
                $epicId = (int) $col['epic_id'];
                $taskIds = array_values(array_unique(array_map('intval', $col['task_ids'])));
                $taskIds = array_values(array_filter($taskIds, fn ($id) => $id > 0));
                foreach ($taskIds as $index => $taskId) {
                    Task::withTrashed()->where('id', $taskId)->update([
                        'epic_id' => $epicId,
                        'position' => ($index + 1) * 10,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($project->trashed()) {
            abort(403, __('ui.error_cannot_view_deleted'));
        }

        $hasAccess = $project->visibility === Project::VISIBILITY_PUBLIC
            || $project->owner_id === (int) $user->id
            || $project->accessUsers()->where('users.id', $user->id)->exists();

        if (!$hasAccess) {
            abort(403, __('ui.error_no_project_access'));
        }
    }
}
