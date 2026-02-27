<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage access: {{ $project->name }}</h2>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Project</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="p-4 bg-green-100 border border-green-300 text-green-800 rounded">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="p-4 bg-red-100 border border-red-300 text-red-800 rounded">{{ session('error') }}</div>
        @endif

        {{-- Set project owner --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Project owner</h3>
            <form method="POST" action="{{ route('projects.owner.update', $project) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div class="min-w-[200px]">
                    <label for="owner_id" class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                    <select id="owner_id" name="owner_id" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($allUsers as $u)
                            <option value="{{ $u->id }}" {{ $project->owner_id == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Update owner</button>
            </form>
        </div>

        {{-- Add user to project --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Add user to project</h3>
            @if($availableUsers->isEmpty())
                <p class="text-gray-500">All users already have access. Add more users in <a href="{{ route('admin.users.index') }}" class="text-indigo-600 hover:underline">Admin → Users</a> first.</p>
            @else
                <form method="POST" action="{{ route('projects.access.add', $project) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-[200px]">
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <select id="user_id" name="user_id" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            <option value="">Select user…</option>
                            @foreach($availableUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select id="role" name="role" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <button type="submit" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Add</button>
                </form>
            @endif
        </div>

        {{-- Users with access --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Users with access</h3>
            <ul class="space-y-3">
                @if($project->owner)
                    <li class="flex items-center gap-3 py-2 border-b border-gray-100">
                        <span class="font-medium">{{ $project->owner->name }}</span>
                        <span class="badge bg-primary">Owner</span>
                        <span class="text-sm text-gray-500">(project owner)</span>
                    </li>
                @endif
                @foreach($project->accessUsers as $u)
                    @if(!$project->owner_id || $u->id != $project->owner_id)
                        <li class="flex flex-wrap items-center gap-3 py-2 border-b border-gray-100">
                            <span class="font-medium">{{ $u->name }}</span>
                            <form method="POST" action="{{ route('projects.access.update', [$project, $u]) }}" class="inline flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="role" class="text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                                    <option value="viewer" {{ (isset($u->pivot->role) && $u->pivot->role === 'viewer') ? 'selected' : '' }}>Viewer</option>
                                    <option value="editor" {{ (isset($u->pivot->role) && $u->pivot->role === 'editor') ? 'selected' : '' }}>Editor</option>
                                </select>
                            </form>
                            <form method="POST" action="{{ route('projects.access.remove', [$project, $u]) }}" class="inline" onsubmit="return confirm('Remove this user from the project?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                            </form>
                        </li>
                    @endif
                @endforeach
            </ul>
            @if(!$project->owner && $project->accessUsers->isEmpty())
                <p class="text-gray-500">No users with access yet. Set an owner above or add users.</p>
            @endif
        </div>
    </div>
</x-app-layout>
