<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prompt: {{ $prompt->title ?: $prompt->agent_type . ' v' . $prompt->version }}</h2>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('projects.show', $task->epic->project) }}" class="hover:underline">{{ $task->epic->project->name }}</a>
                    &rarr;
                    <a href="{{ route('epics.show', $task->epic) }}" class="hover:underline">{{ $task->epic->title }}</a>
                    &rarr;
                    <a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('prompts.edit', [$task, $prompt]) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">Edit</a>
                <a href="{{ route('tasks.show', $task) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Task</a>
            </div>
        </div>
    </x-slot>

    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
            <div>
                <span class="text-xs text-gray-500 uppercase">Agent</span>
                <div class="mt-1 font-medium">{{ $prompt->agent_type }}</div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase">Format</span>
                <div class="mt-1">{{ $prompt->format_type }}</div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase">Version</span>
                <div class="mt-1">{{ $prompt->version }}</div>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase">Updated</span>
                <div class="mt-1">{{ $prompt->updated_at->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t">
            <label class="block text-sm font-medium text-gray-500 mb-1">Content</label>
            <textarea id="prompt-content" readonly rows="20" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $prompt->content }}</textarea>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('prompt-content').value).then(() => this.textContent = 'Copied!').catch(() => {}); setTimeout(() => this.textContent = 'Copy to Clipboard', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Copy to Clipboard</button>
        </div>
    </div>
</x-app-layout>
