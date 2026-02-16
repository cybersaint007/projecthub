<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import Backlog</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('imports.backlog.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-6">
                    <x-input-label for="file" value="JSON File" />
                    <input 
                        id="file" 
                        name="file" 
                        type="file" 
                        accept=".json,application/json"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        required 
                    />
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    <p class="mt-1 text-sm text-gray-500">Maximum file size: 2MB. File must be a valid JSON file.</p>
                </div>

                <div class="mb-6">
                    <label class="flex items-center">
                        <input 
                            type="checkbox" 
                            name="dry_run" 
                            value="1"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            {{ old('dry_run') ? 'checked' : '' }}
                        >
                        <span class="ml-2 text-sm text-gray-700">Dry run (preview only, no changes to database)</span>
                    </label>
                </div>

                @if ($errors->has('import'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded">
                        <strong>Import Error:</strong> {{ $errors->first('import') }}
                    </div>
                @endif

                @if (session('status') && session('result'))
                    @php
                        $result = session('result');
                        $isDryRun = session('isDryRun', false);
                    @endphp
                    <div class="mb-4 p-4 {{ $isDryRun ? 'bg-blue-100 border-blue-300 text-blue-800' : 'bg-green-100 border-green-300 text-green-800' }} rounded">
                        <div class="font-semibold mb-2">{{ session('status') }}</div>
                        
                        @if ($isDryRun)
                            <div class="mt-2">
                                <p class="font-medium">Preview:</p>
                                <ul class="list-disc list-inside mt-1 space-y-1">
                                    <li>1 project: {{ $result->projectCode }} - (preview)</li>
                                    <li>{{ $result->epicsCreated }} epic(s)</li>
                                    <li>{{ $result->tasksCreated }} task(s)</li>
                                </ul>
                                <p class="mt-2 text-sm italic">No changes were made to the database.</p>
                            </div>
                        @else
                            <div class="mt-2">
                                <p class="font-medium">Import Summary:</p>
                                <ul class="list-disc list-inside mt-1 space-y-1">
                                    <li>Project: {{ $result->projectCreated ? '1' : '0' }} ({{ $result->projectCode }})</li>
                                    <li>Epics: {{ $result->epicsCreated }}</li>
                                    <li>Tasks: {{ $result->tasksCreated }}</li>
                                    <li>Time: {{ $result->elapsedTime }}s</li>
                                </ul>
                            </div>
                            @if (!empty($result->messages))
                                <div class="mt-3 pt-3 border-t border-green-200">
                                    <p class="font-medium text-sm">Details:</p>
                                    <ul class="list-none mt-1 space-y-1 text-sm">
                                        @foreach ($result->messages as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                <div class="flex items-center gap-4">
                    <x-primary-button>Import</x-primary-button>
                    <a href="{{ route('projects.index') }}" class="text-gray-600 hover:text-gray-900">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-gray-50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 mb-2">JSON Format Example</h3>
            <pre class="text-xs bg-white p-3 rounded border overflow-x-auto"><code>{
  "project": {
    "code": "PH-CORE",
    "name": "ProjectHub Core Build",
    "description": "Core backlog import",
    "owner_email": "user@example.com"
  },
  "epics": [
    {
      "title": "Portal-only Auth",
      "description": "Portal is IdP",
      "owner_email": "user@example.com",
      "tasks": [
        {
          "title": "Admin create users",
          "description": "",
          "assignee_email": "user@example.com"
        }
      ]
    }
  ]
}</code></pre>
            <p class="mt-2 text-sm text-gray-600">
                <strong>Note:</strong> All email addresses must exist in the users table. Project code must be unique.
            </p>
        </div>
    </div>
</x-app-layout>
