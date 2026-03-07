<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import Backlog &mdash; {{ $project->name }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('backlog.export-json', $project) }}" class="px-3 py-2 bg-indigo-50 text-indigo-700 text-sm rounded hover:bg-indigo-100 font-semibold">Export Current JSON</a>
                <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Project</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-6">

        @if ($errors->has('import'))
            <div class="p-4 bg-red-100 border border-red-300 text-red-800 rounded">
                <strong>Error:</strong> {{ $errors->first('import') }}
            </div>
        @endif

        {{-- Merge Preview --}}
        @if (session('merge_preview'))
            @php $preview = session('merge_preview'); @endphp
            <div class="bg-white shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                <h3 class="font-semibold text-green-800 mb-3">Preview: Merge</h3>
                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <p class="font-medium text-gray-600">Epics</p>
                        <p><span class="text-green-700 font-semibold">+{{ $preview['epics_added'] }} new</span>, {{ $preview['epics_updated'] }} updated</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-600">Tasks</p>
                        <p><span class="text-green-700 font-semibold">+{{ $preview['tasks_added'] }} new</span>, {{ $preview['tasks_updated'] }} updated</p>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4">{{ $preview['note'] }}</p>

                <form method="POST" action="{{ route('backlog.import-merge.apply', $project) }}">
                    @csrf
                    <input type="hidden" name="confirmed_json" value="{{ session('import_json') }}">
                    <div class="flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 font-semibold">
                            Apply Merge
                        </button>
                        <a href="{{ route('backlog.import-replace', $project) }}" class="px-4 py-2 border text-sm rounded hover:bg-gray-50">Cancel</a>
                    </div>
                </form>
            </div>
        @endif

        {{-- Replace Preview --}}
        @if (session('preview'))
            @php $preview = session('preview'); @endphp
            <div class="bg-white shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                <h3 class="font-semibold text-red-800 mb-3">Preview: Replace</h3>
                <div class="grid grid-cols-3 gap-4 text-sm mb-4">
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
                        @php $ed = $preview['changes']['epics_delta']; $td = $preview['changes']['tasks_delta']; @endphp
                        <p>{{ $ed >= 0 ? '+' : '' }}{{ $ed }} epics, {{ $td >= 0 ? '+' : '' }}{{ $td }} tasks</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('backlog.import-replace.apply', $project) }}">
                    @csrf
                    <input type="hidden" name="confirmed_json" value="{{ session('import_json') }}">

                    <label class="flex items-center mb-3">
                        <input type="checkbox" name="backup" value="1" checked
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Create backup before replacing (recommended)</span>
                    </label>

                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800 mb-3">
                        <strong>Warning:</strong> This soft-deletes all current epics and tasks, then recreates them from JSON. Work logs and artifacts on deleted tasks are preserved in the database but detached.
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 font-semibold"
                            onclick="return confirm('Are you sure? This will replace the entire backlog.')">
                            Apply Replace
                        </button>
                        <a href="{{ route('backlog.import-replace', $project) }}" class="px-4 py-2 border text-sm rounded hover:bg-gray-50">Cancel</a>
                    </div>
                </form>
            </div>
        @endif

        {{-- Import Form --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-1">Import JSON</h3>
            <p class="text-sm text-gray-500 mb-4">Paste or upload a backlog JSON, then choose Merge or Replace.</p>

            <form method="POST" action="{{ route('backlog.import-replace.preview', $project) }}" enctype="multipart/form-data" id="import-form">
                @csrf

                <div class="mb-4">
                    <x-input-label for="json_payload" value="Paste JSON" />
                    <textarea
                        id="json_payload"
                        name="json_payload"
                        rows="14"
                        placeholder='{"epics": [{"title": "Epic 1", "tasks": [{"title": "Task 1"}]}]}'
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                    >{{ old('json_payload') }}</textarea>
                </div>

                <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
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

                <div class="flex gap-3">
                    {{-- Merge: submit to merge preview route --}}
                    <button type="submit"
                        formaction="{{ route('backlog.import-merge.preview', $project) }}"
                        class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 font-semibold">
                        Preview Merge
                    </button>

                    {{-- Replace: submit to replace preview route --}}
                    <button type="submit"
                        formaction="{{ route('backlog.import-replace.preview', $project) }}"
                        class="px-4 py-2 bg-red-100 text-red-700 text-sm rounded hover:bg-red-200 font-semibold">
                        Preview Replace
                    </button>
                </div>
            </form>
        </div>

        {{-- Help --}}
        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
            <h3 class="font-semibold text-gray-800 mb-2">Merge vs Replace</h3>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="font-medium text-green-700 mb-1">Merge (recommended)</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>Matches epics/tasks by <code>id</code>, then by title</li>
                        <li>Updates fields on matched items in-place</li>
                        <li>Adds new epics/tasks from the JSON</li>
                        <li>Never deletes — logs, artifacts, prompts are safe</li>
                    </ul>
                </div>
                <div>
                    <p class="font-medium text-red-700 mb-1">Replace</p>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>Soft-deletes all existing epics and tasks</li>
                        <li>Recreates everything from the JSON</li>
                        <li>Optional auto-backup before replacing</li>
                        <li>Use only when you want a clean slate</li>
                    </ul>
                </div>
            </div>
            <p class="text-xs text-gray-500">Tip: use <strong>Export Current JSON</strong> to get a file with <code>id</code> fields already filled in — editing that file guarantees accurate matching on Merge.</p>
        </div>

    </div>
</x-app-layout>
