{{-- Work Logs — add form + timeline. Each entry supports inline editing via Alpine. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ __('ui.work_logs') }}</h3>

    {{-- Add log form --}}
    <form method="POST" action="{{ route('task-logs.store', $task) }}" class="mb-4 p-3 border rounded-lg bg-gray-50">
        @csrf
        <input type="hidden" name="log_type" value="manual" />
        <div class="space-y-2">
            <textarea name="content" rows="10"
                class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="{{ __('ui.add_log_placeholder') }}" required></textarea>
            <x-input-error :messages="$errors->get('content')" class="mt-1" />
            <button type="submit"
                class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">{{ __('ui.add_log') }}</button>
        </div>
    </form>

    @if($task->taskLogs->isEmpty())
        <p class="text-sm text-gray-400 italic">{{ __('ui.no_logs_yet') }}</p>
    @else
        <div class="space-y-0 border-l-2 border-gray-200 pl-4">
            @foreach($task->taskLogs as $log)
                <div class="relative pb-3 last:pb-0" x-data="{ open: {{ $task->taskLogs->count() === 1 ? 'true' : 'false' }}, editing: false }">
                    <span class="absolute -left-4 top-1.5 h-2 w-2 rounded-full {{ $log->log_type === 'manual' ? 'bg-indigo-500' : ($log->log_type === 'ai' ? 'bg-purple-500' : 'bg-gray-400') }}"
                          aria-hidden="true"></span>
                    <div class="ml-2">
                        <div class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer select-none" @click="if (!editing) open = !open">
                            <svg class="w-3 h-3 text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="px-1.5 py-0.5 rounded {{ $log->log_type === 'manual' ? 'bg-indigo-100 text-indigo-700' : ($log->log_type === 'ai' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600') }}">{{ $log->log_type }}</span>
                            <span>{{ $log->created_at->format('M j, Y H:i') }}</span>
                            @if($log->user)
                                <span>{{ $log->user->name }}</span>
                            @endif
                            <span class="text-gray-400 truncate max-w-xs" x-show="!open">— {{ Str::limit($log->content, 80) }}</span>
                            <button type="button" @click.stop="editing = !editing; if (editing) open = true"
                                class="ml-auto text-[11px] text-indigo-600 hover:underline">{{ __('ui.edit') }}</button>
                            <form method="POST" action="{{ route('task-logs.destroy', $log) }}"
                                  onsubmit="return confirm('{{ __('ui.confirm_delete_log') }}')"
                                  class="inline" @click.stop>
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[11px] text-red-500 hover:underline">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                        <div x-show="open && !editing" x-transition class="mt-1">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $log->content }}</p>
                        </div>
                        <div x-show="editing" class="mt-2">
                            <form method="POST" action="{{ route('task-logs.update', $log) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <textarea name="content" rows="10"
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required>{{ old('content', $log->content) }}</textarea>
                                <div class="flex items-center gap-2">
                                    <button type="submit"
                                        class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">{{ __('ui.save') }}</button>
                                    <button type="button" @click="editing = false"
                                        class="px-3 py-1.5 text-xs border rounded hover:bg-gray-50">{{ __('ui.cancel') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
