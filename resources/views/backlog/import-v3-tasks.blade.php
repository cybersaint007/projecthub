<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.import_tasks') }} &mdash; {{ $epic->title }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Add or update tasks inside this epic. Existing tasks matched by <code class="bg-gray-100 px-1 rounded">id</code>, <code class="bg-gray-100 px-1 rounded">external_key</code>, or title are updated; new ones are created.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('backlog.epic.export-v3', $epic) }}" class="px-3 py-2 bg-indigo-50 text-indigo-700 text-sm rounded hover:bg-indigo-100 font-semibold">{{ __('ui.export_epic') }}</a>
                <a href="{{ route('epics.show', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Epic</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-6">

        @if ($errors->has('import'))
            <div class="p-4 bg-red-100 border border-red-300 text-red-800 rounded">
                <strong>Validation error:</strong> {{ $errors->first('import') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-1">Epic-level JSON</h3>
            <p class="text-sm text-gray-500 mb-4">
                Paste a JSON exported via <strong>{{ __('ui.export_epic') }}</strong>. The epic metadata in the JSON is ignored — tasks are always imported into <strong>{{ $epic->title }}</strong>.
            </p>

            <form method="POST" action="{{ route('backlog.epic.import-tasks.apply', $epic) }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload JSON file</label>
                    <input type="file" name="file" accept=".json,.txt"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>

                <div class="flex items-center gap-3 mb-4 text-sm text-gray-400">
                    <span class="flex-1 border-t border-gray-200"></span>
                    <span>or paste below</span>
                    <span class="flex-1 border-t border-gray-200"></span>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Paste JSON</label>
                    <textarea name="json_payload" rows="18"
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder='{
  "schema_version": "3.0",
  "epic": {
    "title": "{{ $epic->title }}",
    "tasks": []
  }
}'>{{ old('json_payload') }}</textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('epics.show', $epic) }}"
                        class="px-4 py-2 border text-sm rounded hover:bg-gray-50">Cancel</a>
                    <button type="submit"
                        class="px-6 py-2 bg-green-600 text-white text-sm font-semibold rounded hover:bg-green-700">
                        {{ __('ui.import_tasks') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-gray-50 border border-gray-200 sm:rounded-lg p-6 text-sm text-gray-600">
            <h4 class="font-semibold text-gray-700 mb-2">Epic JSON format</h4>
            <pre class="text-xs bg-white border border-gray-200 rounded p-4 overflow-auto">{
  "schema_version": "3.0",
  "epic": {
    "title": "{{ $epic->title }}",
    "tasks": [
      {
        "external_key": "task-1",
        "title": "Task One",
        "status": "TODO",
        "prompts": [
          {
            "agent_type": "claude_code",
            "format_type": "structured",
            "content": "Implement this."
          }
        ]
      }
    ]
  }
}</pre>
        </div>

    </div>
</x-app-layout>
