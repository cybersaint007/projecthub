<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
                    @if($project->trashed())
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">Deleted</span>
                    @endif
                    @include('projects.partials.access-badges', ['project' => $project, 'user' => auth()->user()])
                </div>
                @if($project->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $project->description }}</p>
                @endif
            </div>
            @php $userRole = $project->roleFor(auth()->user()); @endphp
            <div class="flex gap-2 flex-wrap">
                @if($project->trashed())
                    @if(Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700" onclick="return confirm('Are you sure you want to restore this project? This will also restore all related epics and tasks.')">Restore Project</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('project-files.index', $project) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Files</a>
                    @if(($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin())
                        <a href="{{ route('epics.create', $project) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">New Epic</a>
                    @endif
                    @if(($userRole && $userRole !== 'viewer') || Auth::user()->isAdmin())
                        <a href="{{ route('projects.edit', $project) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Edit</a>
                    @endif
                    @if($userRole === 'owner' || Auth::user()->isAdmin())
                        <a href="{{ route('projects.access', $project) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Manage access</a>
                    @endif
                    @if(Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('projects.destroy', $project) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700" onclick="return confirm('Are you sure you want to delete this project? This will also delete all related epics and tasks. This action can be undone by restoring the project.')">Delete Project</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </x-slot>

    @if($project->users->isNotEmpty() && Auth::user()->isAdmin())
        <div class="mb-4 bg-white shadow-sm sm:rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Assigned Users</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($project->users as $user)
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">{{ $user->name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @php
        $userRole = $project->roleFor(auth()->user());
        $viewMode = request('view', 'list');
        if (!in_array($viewMode, ['list', 'kanban'], true)) {
            $viewMode = 'list';
        }
        $canUpdate = ($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin();
    @endphp

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-lg font-medium">Epics</h3>
                <div class="flex rounded-lg border border-gray-200 p-0.5 bg-gray-50" role="group">
                    <a href="{{ route('projects.show', [$project, 'view' => 'list']) }}" class="px-3 py-1.5 text-sm font-medium rounded-md {{ $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">List</a>
                    <a href="{{ route('projects.show', [$project, 'view' => 'kanban']) }}" class="px-3 py-1.5 text-sm font-medium rounded-md {{ $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-800' }}">Kanban</a>
                </div>
            </div>
            @if($viewMode === 'kanban')
                @include('projects.partials.kanban-view', ['project' => $project, 'userRole' => $userRole])
            @else
                @include('projects.partials.list-view', ['project' => $project, 'userRole' => $userRole])
            @endif
        </div>
    </div>

    @if($canUpdate && !$project->trashed())
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" crossorigin="anonymous"></script>
        <script src="{{ asset('js/reorder.js') }}"></script>
        <script>
            window.ProjectReorder = {
                projectId: {{ $project->id }},
                csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                canUpdate: true,
                viewMode: {{ json_encode($viewMode) }}
            };
        </script>
    @endif
</x-app-layout>
