{{-- Saved AI Prompts — card layout per prompt. Add Prompt modal is included here. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5" x-data="{}">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('ui.ai_prompts') }}</h3>
        <button type="button" @click="$dispatch('open-modal', 'add-prompt')"
                class="text-xs px-2.5 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">
            + {{ __('ui.add_prompt') }}
        </button>
    </div>

    <p class="text-xs text-gray-400 mb-3">{{ __('ui.ai_prompts_description') }}</p>

    @if($task->taskPrompts->isEmpty())
        <p class="text-sm text-gray-400 italic">{{ __('ui.no_prompts_yet') }}</p>
    @else
        <div class="space-y-2">
            @foreach($task->taskPrompts->sortByDesc('updated_at') as $p)
                <div class="border rounded-lg p-3 bg-gray-50">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center flex-wrap gap-1.5">
                                <span class="text-xs font-medium px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded">{{ $p->agent_type }}</span>
                                <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-600 rounded">{{ $p->format_type }}</span>
                                @if($p->title)
                                    <span class="text-xs font-medium text-gray-700 truncate">{{ $p->title }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400">v{{ $p->version }} &middot; {{ $p->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0 text-xs">
                            <a href="{{ route('prompts.show', [$task, $p]) }}" class="text-indigo-600 hover:underline">{{ __('ui.view') }}</a>
                            <a href="{{ route('prompts.edit', [$task, $p]) }}" class="text-indigo-600 hover:underline">{{ __('ui.edit') }}</a>
                            <form method="POST" action="{{ route('prompts.duplicate', [$task, $p]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-indigo-600 hover:underline">{{ __('ui.duplicate') }}</button>
                            </form>
                            <form method="POST" action="{{ route('prompts.destroy', [$task, $p]) }}" class="inline"
                                  onsubmit="return confirm('{{ __('ui.confirm_delete_prompt') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:underline">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Add Prompt Modal --}}
@php $addPromptErrors = $errors->has('agent_type') || $errors->has('format_type') || $errors->has('title') || $errors->has('content'); @endphp
<x-modal name="add-prompt" :show="$addPromptErrors" maxWidth="2xl">
    <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">{{ __('ui.add_prompt') }}</h3>
        <form method="POST" action="{{ route('prompts.store', $task) }}" id="add-prompt-form">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="add_agent_type" :value="__('ui.agent')" />
                    <select id="add_agent_type" name="agent_type"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        @foreach(config('task_prompts.agent_types', \App\Models\TaskPrompt::AGENT_TYPES) as $a)
                            <option value="{{ $a }}" {{ old('agent_type') === $a ? 'selected' : '' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('agent_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="add_format_type" :value="__('ui.format')" />
                    <select id="add_format_type" name="format_type"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        @foreach(config('task_prompts.format_types', \App\Models\TaskPrompt::FORMAT_TYPES) as $f)
                            <option value="{{ $f }}" {{ old('format_type', 'structured') === $f ? 'selected' : '' }}>{{ $f }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('format_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="add_title" :value="__('ui.title_optional')" />
                    <x-text-input id="add_title" name="title" type="text" class="block w-full" :value="old('title')" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="add_content" :value="__('ui.content')" />
                    <textarea id="add_content" name="content" rows="12"
                        class="mt-1 block w-full font-mono text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required placeholder="Paste or type your prompt...">{{ old('content') }}</textarea>
                    <x-input-error :messages="$errors->get('content')" class="mt-2" />
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <x-primary-button>{{ __('ui.add_prompt') }}</x-primary-button>
                <button type="button" @click="$dispatch('close-modal', 'add-prompt')"
                    class="px-4 py-2 border rounded hover:bg-gray-50">{{ __('ui.cancel') }}</button>
            </div>
        </form>
    </div>
</x-modal>
