<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import/Replace Backlog &mdash; {{ $project->name }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('backlog.export-json', $project) }}" class="px-3 py-2 bg-indigo-50 text-indigo-700 text-sm rounded hover:bg-indigo-100 font-semibold">Export Current JSON</a>
                <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Project</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

            @if ($errors->has('import'))
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded">
                    <strong>Error:</strong> {{ $errors->first('import') }}
                </div>
            @endif

            @if (session('preview'))
                @php $preview = session('preview'); @endphp
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
                    <h3 class="font-semibold text-blue-800 mb-3">Preview: Replace Backlog</h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="font-medium text-gray-600">Current</p>
                            <p>{{ $preview['current']['epics'] }} epics, {{ $preview['current']['tasks'] }} tasks</p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-600">After Replace</p>
                            <p>{{ $preview['incoming']['epics'] }} epics, {{ $preview['incoming']['tasks'] }} tasks</p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-600">Change</p>
                            <p>
                                @php $ed = $preview['changes']['epics_delta']; $td = $preview['changes']['tasks_delta']; @endphp
                                {{ $ed >= 0 ? '+' : '' }}{{ $ed }} epics,
                                {{ $td >= 0 ? '+' : '' }}{{ $td }} tasks
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('backlog.import-replace.apply', $project) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="confirmed_json" value="{{ session('import_json') }}">

                        <div class="mb-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="backup" value="1" checked
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Create backup before replacing (recommended)</span>
                            </label>
                        </div>

                        <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800 mb-3">
                            <strong>Warning:</strong> This will soft-delete all current epics and tasks, then create new ones from the JSON. Existing logs and artifacts on deleted tasks are preserved.
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 font-semibold"
                                onclick="return confirm('Are you sure? This will replace the entire backlog.')">
                                Apply Replace
                            </button>
                            <a href="{{ route('backlog.import-replace', $project) }}" class="px-4 py-2 border text-sm rounded hover:bg-gray-50">Cancel</a>
                        </div>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('backlog.import-replace.preview', $project) }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-6">
                    <x-input-label for="json_payload" value="Paste JSON" />
                    <textarea
                        id="json_payload"
                        name="json_payload"
                        rows="14"
                        placeholder='{"epics": [{"title": "Epic 1", "tasks": [{"title": "Task 1"}]}]}'
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                    >{{ old('json_payload') }}</textarea>
                </div>

                <div class="mb-6 flex items-center gap-2 text-sm text-gray-500">
                    <span class="border-t flex-1"></span>
                    <span>or upload a file</span>
                    <span class="border-t flex-1"></span>
                </div>

                <div class="mb-6">
                    <x-input-label for="file" value="JSON File" />
                    <input
                        id="file"
                        name="file"
                        type="file"
                        accept=".json,application/json"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>

                <x-primary-button>Preview Changes</x-primary-button>
            </form>
        </div>

        <div class="mt-6 bg-gray-50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 mb-2">JSON Format</h3>
            <p class="text-sm text-gray-600 mb-2">Use the <strong>Export JSON</strong> button to get the current backlog, modify it, then import it back. The format supports all task fields:</p>
            <pre class="text-xs bg-white p-3 rounded border overflow-x-auto"><code>{
  "epics": [
    {
      "title": "Epic Title",
      "description": "Optional",
      "milestone_tag": "v1.0",
      "position": 10,
      "tasks": [
        {
          "title": "Task Title",
          "description": "...",
          "status": "TODO",
          "agent": "claude_code",
          "priority": 3,
          "tags": ["backend", "api"],
          "context": "...",
          "instructions": "...",
          "acceptance_criteria": "...",
          "position": 10,
          "prompts": [
            {
              "agent_type": "claude_code",
              "format_type": "structured",
              "title": "Optional title",
              "version": 1,
              "content": "The prompt content..."
            }
          ]
        }
      ]
    }
  ]
}</code></pre>
        </div>
    </div>
</x-app-layout>
