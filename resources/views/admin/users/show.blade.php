<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500">{{ $user->email }}
                    @if($user->is_admin)
                        <span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded">Admin</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">Edit</a>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password?')">
                    @csrf
                    <button type="submit" class="px-3 py-2 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600">Reset Password</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">Project Assignments</h3>

        <form method="POST" action="{{ route('admin.users.sync-projects', $user) }}">
            @csrf
            @if($projects->isEmpty())
                <p class="text-gray-500 mb-4">No projects exist yet. Create a project first.</p>
            @else
                <div class="space-y-2 mb-4">
                    @foreach($projects as $project)
                        <label class="flex items-center">
                            <input type="checkbox" name="projects[]" value="{{ $project->id }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                {{ in_array($project->id, $assignedProjectIds) ? 'checked' : '' }}>
                            <span class="ms-2 text-sm text-gray-700">{{ $project->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-primary-button>Update Assignments</x-primary-button>
            @endif
        </form>
    </div>
</x-app-layout>
