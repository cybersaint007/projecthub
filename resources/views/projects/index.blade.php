<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Projects</h2>
            @if(Auth::user()->isAdmin())
                <a href="{{ route('projects.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">New Project</a>
            @endif
        </div>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        @if($projects->isEmpty())
            <p class="p-6 text-gray-500">No projects found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Epics</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($projects as $project)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-medium">
                                <a href="{{ route('projects.show', $project) }}" class="text-indigo-600 hover:underline">{{ $project->name }}</a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($project->description, 60) }}</td>
                            <td class="px-6 py-4 text-sm">{{ $project->epics_count }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('project-files.index', $project) }}" class="text-gray-600 hover:text-gray-900 mr-3">Files</a>
                                @if(Auth::user()->isAdmin())
                                    <a href="{{ route('projects.edit', $project) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
