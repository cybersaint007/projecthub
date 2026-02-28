<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }}</h2>
                <p class="text-sm text-gray-500">{{ $user->email }}
                    @if($user->is_admin)
                        <span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded">{{ __('ui.admin_badge') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.users.index') }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_users') }}</a>
                <a href="{{ route('admin.users.edit', $user) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">{{ __('ui.edit') }}</a>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" onsubmit="return confirm('{{ __('ui.confirm_reset_password', ['name' => $user->name]) }}')">
                    @csrf
                    <button type="submit" class="px-3 py-2 bg-yellow-500 text-white text-sm rounded hover:bg-yellow-600">{{ __('ui.reset_password') }}</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium mb-4">{{ __('ui.project_assignments') }}</h3>
        <p class="text-sm text-gray-500 mb-4">{!! __('ui.project_assignments_desc') !!}</p>

        <form method="POST" action="{{ route('admin.users.sync-projects', $user) }}">
            @csrf
            @if($projects->isEmpty())
                <p class="text-gray-500 mb-4">{{ __('ui.no_projects_exist') }}</p>
            @else
                <div class="space-y-3 mb-4">
                    @foreach($projects as $project)
                        @php
                            $assigned = $assignedProjects->has($project->id);
                            $currentRole = $assigned && $assignedProjects[$project->id]->pivot ? $assignedProjects[$project->id]->pivot->role : 'viewer';
                        @endphp
                        <label class="flex items-center gap-3 flex-wrap">
                            <input type="checkbox" name="projects[]" value="{{ $project->id }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                {{ $assigned ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700 min-w-[120px]">{{ $project->name }}</span>
                            <select name="roles[{{ $project->id }}]" class="text-sm border-gray-300 rounded shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="viewer" {{ $currentRole === 'viewer' ? 'selected' : '' }}>{{ __('ui.viewer') }}</option>
                                <option value="editor" {{ $currentRole === 'editor' ? 'selected' : '' }}>{{ __('ui.editor') }}</option>
                            </select>
                        </label>
                    @endforeach
                </div>
                <x-primary-button>{{ __('ui.update_assignments') }}</x-primary-button>
            @endif
        </form>
    </div>
</x-app-layout>
