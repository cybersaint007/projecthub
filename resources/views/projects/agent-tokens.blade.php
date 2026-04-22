<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.agent_tokens') }}: {{ $project->name }}</h2>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_project') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="p-4 bg-green-100 border border-green-300 text-green-800 rounded">{{ session('status') }}</div>
        @endif

        @if (session('new_token'))
            <div class="p-4 bg-yellow-50 border border-yellow-300 rounded" x-data="{ copied: false }">
                <p class="text-sm font-semibold text-yellow-800 mb-2">{{ __('ui.token_created_notice') }}</p>
                <div class="flex items-center gap-3">
                    <code class="flex-1 font-mono text-sm bg-white border border-yellow-300 rounded px-3 py-2 break-all select-all">{{ session('new_token') }}</code>
                    <button
                        @click="navigator.clipboard.writeText('{{ session('new_token') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="shrink-0 px-3 py-2 text-sm border border-yellow-400 rounded hover:bg-yellow-100 text-yellow-800"
                        x-text="copied ? '{{ __('ui.copied') }}' : '{{ __('ui.copy') }}'">
                        {{ __('ui.copy') }}
                    </button>
                </div>
            </div>
        @endif

        {{-- Create token --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('ui.create_agent_token') }}</h3>
            <form method="POST" action="{{ route('projects.agent-tokens.store', $project) }}" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('ui.token_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="{{ __('ui.token_name_placeholder') }}">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.generate_token') }}</button>
            </form>
        </div>

        {{-- Existing tokens --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">{{ __('ui.active_tokens') }}</h3>
            @if ($tokens->isEmpty())
                <p class="text-gray-500 text-sm">{{ __('ui.no_agent_tokens') }}</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">{{ __('ui.name') }}</th>
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">{{ __('ui.created') }}</th>
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">{{ __('ui.last_used') }}</th>
                            <th class="text-left py-2 pr-4 font-medium text-gray-700">{{ __('ui.expires') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 font-medium">{{ $token->name }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $token->created_at->format('Y-m-d') }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : '—' }}</td>
                                <td class="py-2 pr-4 text-gray-500">
                                    @if ($token->expires_at)
                                        @if ($token->isExpired())
                                            <span class="text-red-600 text-xs">{{ __('ui.expired') }} {{ $token->expires_at->format('Y-m-d') }}</span>
                                        @else
                                            {{ $token->expires_at->format('Y-m-d') }}
                                        @endif
                                    @else
                                        {{ __('ui.never') }}
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('projects.agent-tokens.destroy', [$project, $token]) }}"
                                        onsubmit="return confirm('{{ __('ui.revoke_token_confirm', ['name' => addslashes($token->name)]) }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">{{ __('ui.revoke') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Runner quick-start --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium mb-3">{{ __('ui.agent_runner_quickstart') }}</h3>
            <p class="text-sm text-gray-500 mb-3">{!! __('ui.agent_runner_quickstart_desc') !!}</p>
            <pre class="bg-gray-50 border rounded p-4 text-xs font-mono whitespace-pre-wrap">AGENT_API_BASE={{ rtrim(config('app.url'), '/') }}
AGENT_TOKEN=&lt;paste-token-here&gt;
AGENT_PROJECT_ID={{ $project->id }}
AGENT_TYPE=claude_code
AGENT_WORKER_ID={{ gethostname() }}
AGENT_REPO_PATH=/path/to/your/repo</pre>
        </div>
    </div>
</x-app-layout>
