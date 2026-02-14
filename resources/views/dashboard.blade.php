<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">
            {{ Auth::user()->isAdmin() ? 'All Projects' : 'My Projects' }}
        </h3>

        @if($projects->isEmpty())
            <p class="text-gray-500">No projects yet.</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="block p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">{{ $project->name }}</h4>
                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($project->description, 80) }}</p>
                        <div class="mt-2 text-xs text-gray-400">
                            {{ $project->epics_count }} epic(s)
                            @if(Auth::user()->isAdmin())
                                &middot; {{ $project->users_count }} user(s)
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
