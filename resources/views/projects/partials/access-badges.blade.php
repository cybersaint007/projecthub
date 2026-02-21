@php
    $visibility = $project->visibility ?? \App\Models\Project::VISIBILITY_PRIVATE;
    $role = isset($user) ? $project->roleFor($user) : null;
@endphp
<div class="flex flex-wrap items-center gap-2">
    {{-- A) Visibility badge --}}
    @if($visibility === 'private')
        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1 js-bi" aria-hidden="true"></i>Private</span>
    @elseif($visibility === 'shared')
        <span class="badge bg-warning text-dark"><i class="bi bi-people-fill me-1 js-bi" aria-hidden="true"></i>Shared</span>
    @else
        <span class="badge bg-success"><i class="bi bi-globe2 me-1 js-bi" aria-hidden="true"></i>Public</span>
    @endif

    {{-- B) Role badge (only when user has a role) --}}
    @if($role === 'owner')
        <span class="badge bg-primary"><i class="bi bi-crown-fill me-1 js-bi" aria-hidden="true"></i>Owner</span>
    @elseif($role === 'editor')
        <span class="badge bg-info text-dark"><i class="bi bi-pencil-square me-1 js-bi" aria-hidden="true"></i>Editor</span>
    @elseif($role === 'viewer')
        <span class="badge bg-secondary"><i class="bi bi-eye-fill me-1 js-bi" aria-hidden="true"></i>Viewer</span>
    @endif
</div>
