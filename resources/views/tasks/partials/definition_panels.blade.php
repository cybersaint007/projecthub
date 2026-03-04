{{-- Collapsible definition panels: Context · Instructions · Acceptance Criteria
     Default: expanded when content exists, collapsed when empty. --}}
<div class="space-y-3">

    {{-- Context --}}
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
         x-data="{ open: {{ $task->context ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-gray-50 transition-colors">
            <span class="text-sm font-medium text-gray-700">{{ __('ui.context') }}</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-4 border-t">
            @if($task->context)
                <p class="text-sm text-gray-700 whitespace-pre-wrap pt-3">{{ $task->context }}</p>
            @else
                <p class="text-sm text-gray-400 italic pt-3">No context provided.</p>
            @endif
        </div>
    </div>

    {{-- Instructions --}}
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
         x-data="{ open: {{ $task->instructions ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-gray-50 transition-colors">
            <span class="text-sm font-medium text-gray-700">{{ __('ui.instructions') }}</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-4 border-t">
            @if($task->instructions)
                <p class="text-sm text-gray-700 whitespace-pre-wrap pt-3">{{ $task->instructions }}</p>
            @else
                <p class="text-sm text-gray-400 italic pt-3">No instructions provided.</p>
            @endif
        </div>
    </div>

    {{-- Acceptance Criteria --}}
    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
         x-data="{ open: {{ $task->acceptance_criteria ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-gray-50 transition-colors">
            <span class="text-sm font-medium text-gray-700">{{ __('ui.acceptance_criteria') }}</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-cloak class="px-5 pb-4 border-t">
            @if($task->acceptance_criteria)
                <p class="text-sm text-gray-700 whitespace-pre-wrap pt-3">{{ $task->acceptance_criteria }}</p>
            @else
                <p class="text-sm text-gray-400 italic pt-3">No acceptance criteria provided.</p>
            @endif
        </div>
    </div>

</div>
