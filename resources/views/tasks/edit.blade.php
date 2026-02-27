<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Task: {{ $task->title }}</h2>
            <a href="{{ route('tasks.show', $task) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Task</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('tasks.update', $task) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $task->title)" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::STATUSES as $s)
                            <option value="{{ $s }}" {{ old('status', $task->status) === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="agent" value="Agent" />
                    <select id="agent" name="agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::AGENTS as $a)
                            <option value="{{ $a }}" {{ old('agent', $task->agent) === $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="Priority" />
                    <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::priorityOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('priority', (string)$task->priority) === (string)$value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task->description) }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="tags" value="Tags (comma-separated)" />
                <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full" :value="old('tags', is_array($task->tags) ? implode(', ', $task->tags) : '')" />
            </div>

            <div class="mb-4">
                <x-input-label for="context" value="Context" />
                <textarea id="context" name="context" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('context', $task->context) }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="instructions" value="Instructions" />
                <textarea id="instructions" name="instructions" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('instructions', $task->instructions) }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="acceptance_criteria" value="Acceptance Criteria" />
                <textarea id="acceptance_criteria" name="acceptance_criteria" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('acceptance_criteria', $task->acceptance_criteria) }}</textarea>
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Update Task</x-primary-button>
                <a href="{{ route('tasks.show', $task) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
