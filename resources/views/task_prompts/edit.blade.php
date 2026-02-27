<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Prompt</h2>
            <a href="{{ route('tasks.show', $task) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Task</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <p class="text-sm text-gray-500 mb-4">Agent: <strong>{{ $prompt->agent_type }}</strong> · Version: <strong>{{ $prompt->version }}</strong> (read-only)</p>

        <form method="POST" action="{{ route('prompts.update', [$task, $prompt]) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <x-input-label for="format_type" value="Format" />
                <select id="format_type" name="format_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach(config('task_prompts.format_types', \App\Models\TaskPrompt::FORMAT_TYPES) as $f)
                        <option value="{{ $f }}" {{ old('format_type', $prompt->format_type) === $f ? 'selected' : '' }}>{{ $f }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('format_type')" class="mt-2" />
            </div>

            <div class="mb-4">
                <x-input-label for="title" value="Title (optional)" />
                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $prompt->title)" />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div class="mb-4">
                <x-input-label for="content" value="Content" />
                <textarea id="content" name="content" rows="18" class="mt-1 block w-full font-mono text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('content', $prompt->content) }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
            </div>

            <div class="flex gap-2">
                <x-primary-button>Save</x-primary-button>
                <a href="{{ route('tasks.show', $task) }}" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
