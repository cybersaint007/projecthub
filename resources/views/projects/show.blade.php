<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2>
                    @if($project->trashed())
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">Deleted</span>
                    @endif
                </div>
                @if($project->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $project->description }}</p>
                @endif
            </div>
            <div class="flex gap-2">
                @if($project->trashed())
                    @if(Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('projects.restore', $project) }}" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700" onclick="return confirm('Are you sure you want to restore this project? This will also restore all related epics and tasks.')">Restore Project</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('project-files.index', $project) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Files</a>
                    <a href="{{ route('epics.create', $project) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">New Epic</a>
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

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium mb-4">Epics</h3>
            @if($project->epics->isEmpty())
                <p class="text-gray-500">No epics yet.</p>
            @else
                <div class="space-y-3">
                    @foreach($project->epics as $epic)
                        @if($epic->trashed())
                            <div class="border rounded-lg p-4 bg-gray-50 opacity-60">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-medium text-gray-400 line-through">{{ $epic->title }}</span>
                                        <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">Deleted</span>
                                    </div>
                                </div>
                            </div>
                        @else
                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <a href="{{ route('epics.show', $epic) }}" class="font-medium text-indigo-600 hover:underline">{{ $epic->title }}</a>
                                    @if($epic->milestone_tag)
                                        <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded">{{ $epic->milestone_tag }}</span>
                                    @endif
                                    @if($epic->description)
                                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($epic->description, 100) }}</p>
                                    @endif
                                </div>
                                <div class="flex gap-3 items-center">
                                    <a href="{{ route('epics.kanban', $epic) }}" class="text-sm text-indigo-600 hover:underline">Kanban</a>
                                    <span class="text-sm text-gray-400">{{ $epic->tasks->count() }} tasks</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
