<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Webhook Endpoints: {{ $project->name }}</h2>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Project</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="p-4 bg-green-100 border border-green-300 text-green-800 rounded">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="p-4 bg-red-100 border border-red-300 text-red-800 rounded">{{ session('error') }}</div>
        @endif

        {{-- Add webhook endpoint --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Add Webhook Endpoint</h3>
            <form method="POST" action="{{ route('projects.webhooks.store', $project) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-1">URL <span class="text-red-500">*</span></label>
                    <input type="url" id="url" name="url" value="{{ old('url') }}" required
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="https://example.com/webhook">
                    @error('url') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="secret" class="block text-sm font-medium text-gray-700 mb-1">Secret <span class="text-gray-400 font-normal">(optional — used for HMAC-SHA256 signature)</span></label>
                    <input type="text" id="secret" name="secret" value="{{ old('secret') }}"
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="your-secret-key">
                    @error('secret') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Events <span class="text-red-500">*</span></label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="events[]" value="task.ready"
                            {{ in_array('task.ready', old('events', [])) ? 'checked' : 'checked' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700"><code>task.ready</code> — fired when a task transitions to Ready status</span>
                    </label>
                    @error('events') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Add Endpoint</button>
                </div>
            </form>
        </div>

        {{-- Existing endpoints --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Active Endpoints</h3>
            @if ($webhooks->isEmpty())
                <p class="text-gray-500 text-sm">No webhook endpoints configured.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">URL</th>
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">Events</th>
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">Secret</th>
                            <th class="text-left py-2 font-medium text-gray-700">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($webhooks as $webhook)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 font-mono text-xs break-all max-w-xs">{{ $webhook->url }}</td>
                                <td class="py-2 pr-4">
                                    @foreach ($webhook->events as $event)
                                        <span class="inline-block px-2 py-0.5 bg-gray-100 rounded text-xs font-mono">{{ $event }}</span>
                                    @endforeach
                                </td>
                                <td class="py-2 pr-4 text-gray-500">{{ $webhook->secret ? '••••••' : '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($webhook->active)
                                        <span class="text-green-600 text-xs font-medium">Active</span>
                                    @else
                                        <span class="text-gray-400 text-xs">Inactive</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('projects.webhooks.destroy', [$project, $webhook]) }}"
                                        onsubmit="return confirm('Remove this webhook endpoint?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
