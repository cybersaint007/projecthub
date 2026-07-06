<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.new_task') }} — {{ $epic->title }}</h2>
            <a href="{{ route('epics.show', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_epic') }}</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('tasks.store', $epic) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="title" :value="__('ui.title')" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="status" :value="__('ui.status')" />
                    <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::STATUSES as $s)
                            <option value="{{ $s }}" {{ old('status', 'TODO') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <x-input-label for="agent" :value="__('ui.agent')" />
                    <select id="agent" name="agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::AGENTS as $a)
                            <option value="{{ $a }}" {{ old('agent', 'human') === $a ? 'selected' : '' }}>{{ \App\Support\AgentType::label($a) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" :value="__('ui.priority')" />
                    <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Models\Task::priorityOptions() as $value => $label)
                            <option value="{{ $value }}" {{ old('priority', 3) == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <x-input-label for="description" :value="__('ui.description')" />
                <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="tags" :value="__('ui.tags_comma_separated')" />
                <x-text-input id="tags" name="tags" type="text" class="mt-1 block w-full" :value="old('tags')" placeholder="e.g. backend, api, auth" />
            </div>

            <div class="mb-4">
                <x-input-label for="context" :value="__('ui.context_label')" />
                <textarea id="context" name="context" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('context') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="instructions" :value="__('ui.instructions_label')" />
                <textarea id="instructions" name="instructions" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('instructions') }}</textarea>
            </div>

            <div class="mb-4">
                <x-input-label for="acceptance_criteria" :value="__('ui.acceptance_criteria')" />
                <textarea id="acceptance_criteria" name="acceptance_criteria" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('acceptance_criteria') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>{{ __('ui.create_task') }}</x-primary-button>
                <a href="{{ route('epics.show', $epic) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-medium text-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">{{ __('ui.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
