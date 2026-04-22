<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.dashboard') }}</h2>
            @if(Auth::user()->isAdmin())
                <a href="{{ route('backlog.import-v3.global') }}" class="px-3 py-1.5 text-sm bg-green-600 text-white rounded hover:bg-green-700">{{ __('ui.import_project') }}</a>
            @endif
        </div>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">
            {{ Auth::user()->isAdmin() ? __('ui.all_projects') : __('ui.my_projects') }}
        </h3>

        @if($projects->isEmpty())
            <p class="text-gray-500">{{ __('ui.no_projects_yet') }}</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="block p-4 border rounded-lg hover:bg-gray-50 transition">
                        <h4 class="font-semibold text-gray-800">{{ $project->name }}</h4>
                        <p class="text-sm text-gray-500 mt-1">{{ Str::limit($project->description, 80) }}</p>
                        <div class="mt-2 text-xs text-gray-400">
                            {{ __('ui.epic_count', ['count' => $project->epics_count]) }}
                            @if(Auth::user()->isAdmin())
                                &middot; {{ __('ui.user_count', ['count' => $project->users_count]) }}
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
