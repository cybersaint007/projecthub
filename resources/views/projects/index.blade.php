<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.projects') }}</h2>
            <div class="flex items-center gap-3">
                @if(Auth::user()->isAdmin())
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            id="showTrashedToggle"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            checked
                        >
                        <span class="ml-2 text-sm text-gray-700">{{ __('ui.show_deleted_projects') }}</span>
                    </label>
                @endif
                <a href="{{ route('backlog.import-v3.global') }}" class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700">Import V3</a>
                <a href="{{ route('projects.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.new_project') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        @if($projects->isEmpty())
            <p class="p-6 text-gray-500">{{ __('ui.no_projects_found') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-64 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.description') }}</th>
                        <th class="w-16 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">{{ __('ui.epics') }}</th>
                        <th class="w-40 px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="projectsTableBody">
                    @foreach($projects as $project)
                        <tr
                            class="project-row {{ $project->trashed() ? 'trashed-project bg-gray-50 opacity-75' : '' }}"
                            data-trashed="{{ $project->trashed() ? '1' : '0' }}"
                        >
                            <td class="w-64 px-6 py-4 font-medium">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('projects.show', $project) }}" class="text-indigo-600 hover:underline {{ $project->trashed() ? 'line-through' : '' }}">{{ $project->name }}</a>
                                        @if($project->trashed())
                                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">{{ __('ui.deleted') }}</span>
                                        @endif
                                    </div>
                                    @include('projects.partials.access-badges', ['project' => $project, 'user' => auth()->user()])
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($project->description, 80) }}</td>
                            <td class="w-16 px-4 py-4 text-sm">{{ $project->epics_count }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                @if(!$project->trashed())
                                    <a href="{{ route('project-files.index', $project) }}" class="text-gray-600 hover:text-gray-900 mr-3">{{ __('ui.files') }}</a>
                                    @if(Auth::user()->isAdmin())
                                        <a href="{{ route('projects.edit', $project) }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.edit') }}</a>
                                    @endif
                                @else
                                    @if(Auth::user()->isAdmin())
                                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('{{ __('ui.confirm_restore_project') }}')">{{ __('ui.restore') }}</button>
                                        </form>
                                    @endif
                                    <span class="text-gray-400">{{ __('ui.deleted_ago', ['time' => $project->deleted_at->diffForHumans()]) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if(Auth::user()->isAdmin())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toggle = document.getElementById('showTrashedToggle');
                const tableBody = document.getElementById('projectsTableBody');

                if (toggle && tableBody) {
                    // Store preference in localStorage
                    const stored = localStorage.getItem('showTrashedProjects');
                    if (stored !== null) {
                        toggle.checked = stored === 'true';
                    }

                    // Initial state
                    updateVisibility();

                    // Toggle event
                    toggle.addEventListener('change', function() {
                        localStorage.setItem('showTrashedProjects', this.checked);
                        updateVisibility();
                    });

                    function updateVisibility() {
                        const rows = tableBody.querySelectorAll('.trashed-project');
                        rows.forEach(row => {
                            row.style.display = toggle.checked ? '' : 'none';
                        });
                    }
                }
            });
        </script>
    @endif
</x-app-layout>
