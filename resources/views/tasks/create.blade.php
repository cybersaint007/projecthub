<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Task in {{ $epic->title }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('tasks.store', $epic) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::STATUSES as $s)
                            <option value="{{ $s }}" {{ old('status', 'Backlog') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="agent" value="Agent" />
                    <select id="agent" name="agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::AGENTS as $a)
                            <option value="{{ $a }}" {{ old('agent', 'human') === $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="Priority" />
                    <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::PRIORITIES as $p)
                            <option value="{{ $p }}" {{ old('priority', 'medium') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="tags" value="Tags (comma-separated)" />
                <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full" :value="old('tags')" placeholder="e.g. backend, api, auth" />
            </div>

            <div class="mb-4">
                <x-input-label for="context" value="Context (background & constraints)" />
                <textarea id="context" name="context" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('context') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="instructions" value="Instructions (what to do)" />
                <textarea id="instructions" name="instructions" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('instructions') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="acceptance_criteria" value="Acceptance Criteria" />
                <textarea id="acceptance_criteria" name="acceptance_criteria" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('acceptance_criteria') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Create Task</x-primary-button>
                <a href="{{ route('epics.show', $epic) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-medium text-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
