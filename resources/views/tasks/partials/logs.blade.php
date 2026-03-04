{{-- Work Logs — add form + timeline. Each entry supports inline editing via Alpine. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ __('ui.work_logs') }}</h3>

    {{-- Add log form --}}
    <form method="POST" action="{{ route('task-logs.store', $task) }}" class="mb-4 p-3 border rounded-lg bg-gray-50">
        @csrf
        <input type="hidden" name="log_type" value="manual" />
        <div class="space-y-2">
            <textarea name="content" rows="2"
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
                <div class="relative pb-5 last:pb-0" x-data="{ editing: false }">
                    <span class="absolute -left-4 top-1.5 h-2 w-2 rounded-full {{ $log->log_type === 'manual' ? 'bg-indigo-500' : ($log->log_type === 'ai' ? 'bg-purple-500' : 'bg-gray-400') }}"
                          aria-hidden="true"></span>
                    <div class="ml-2">
                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-0.5">
                            <span class="px-1.5 py-0.5 rounded {{ $log->log_type === 'manual' ? 'bg-indigo-100 text-indigo-700' : ($log->log_type === 'ai' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600') }}">{{ $log->log_type }}</span>
                            <span>{{ $log->created_at->format('M j, Y H:i') }}</span>
                            @if($log->user)
                                <span>{{ $log->user->name }}</span>
                            @endif
                            <button type="button" @click="editing = !editing"
                                class="ml-auto text-[11px] text-indigo-600 hover:underline">{{ __('ui.edit') }}</button>
                        </div>
                        <div x-show="!editing">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $log->content }}</p>
                        </div>
                        <div x-show="editing" class="mt-2">
                            <form method="POST" action="{{ route('task-logs.update', $log) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <textarea name="content" rows="3"
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
