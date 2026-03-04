{{--
  Task Detail — "Task Control Center" (Menu / Panel layout)
  ──────────────────────────────────────────────────────────────────────────
  Always visible:
    · partials/header.blade.php   — title, breadcrumb, action buttons
    · partials/info.blade.php     — Status / Agent / Priority / Tags

  Left nav menu (Alpine `active` state, default: 'prompts'):
    EXECUTE   → prompts · generator · logs · artifacts · review
    REFERENCE → description · context · instructions · acceptance
    ──────────  controls

  Right panel:
    Renders the selected section. Each partial keeps its own card wrapper.
    Simple reference sections (context / instructions / acceptance) are
    inlined here — they are text-only and need no dedicated partial.
--}}
<x-app-layout>
    <x-slot name="header">
        @include('tasks.partials.header')
    </x-slot>

    {{-- Always-visible info bar --}}
    @include('tasks.partials.info')

    {{-- Console: menu + panel --}}
    <div class="mt-4 flex bg-white shadow-sm sm:rounded-lg overflow-hidden"
         style="min-height: 640px;"
         x-data="{ active: 'prompts' }">

        {{-- ── Left navigation menu ─────────────────────────────────────── --}}
        <nav class="w-44 flex-shrink-0 border-r bg-gray-50 flex flex-col py-2">

            <p class="px-3 pt-1 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Execute</p>

            @foreach([
                ['id' => 'prompts',   'label' => 'Prompts',   'count' => $task->taskPrompts->count()],
                ['id' => 'generator', 'label' => 'Generator', 'count' => null],
                ['id' => 'logs',      'label' => 'Logs',      'count' => $task->taskLogs->count()],
                ['id' => 'artifacts', 'label' => 'Artifacts', 'count' => $task->artifacts->count()],
                ['id' => 'review',    'label' => 'Review',    'count' => $task->reviews->count()],
            ] as $item)
                <button type="button"
                        @click="active = '{{ $item['id'] }}'"
                        :class="active === '{{ $item['id'] }}'
                            ? 'bg-white border-l-2 border-indigo-500 text-indigo-700 font-medium'
                            : 'border-l-2 border-transparent text-gray-600 hover:bg-white hover:text-gray-800'"
                        class="w-full text-left px-3 py-2 text-sm flex items-center justify-between transition-colors">
                    <span>{{ $item['label'] }}</span>
                    @if($item['count'])
                        <span class="text-[11px] min-w-[18px] text-center px-1 rounded-full bg-gray-200 text-gray-500">{{ $item['count'] }}</span>
                    @endif
                </button>
            @endforeach

            <p class="px-3 pt-3 pb-1 mt-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-t">Reference</p>

            @foreach([
                ['id' => 'description',  'label' => 'Description'],
                ['id' => 'context',      'label' => 'Context'],
                ['id' => 'instructions', 'label' => 'Instructions'],
                ['id' => 'acceptance',   'label' => 'Acceptance'],
            ] as $item)
                <button type="button"
                        @click="active = '{{ $item['id'] }}'"
                        :class="active === '{{ $item['id'] }}'
                            ? 'bg-white border-l-2 border-indigo-500 text-indigo-700 font-medium'
                            : 'border-l-2 border-transparent text-gray-600 hover:bg-white hover:text-gray-800'"
                        class="w-full text-left px-3 py-2 text-sm transition-colors">
                    {{ $item['label'] }}
                </button>
            @endforeach

            <div class="mt-auto border-t pt-1">
                <button type="button"
                        @click="active = 'controls'"
                        :class="active === 'controls'
                            ? 'bg-white border-l-2 border-indigo-500 text-indigo-700 font-medium'
                            : 'border-l-2 border-transparent text-gray-600 hover:bg-white hover:text-gray-800'"
                        class="w-full text-left px-3 py-2 text-sm transition-colors">
                    Controls
                </button>
            </div>
        </nav>

        {{-- ── Right content panel ──────────────────────────────────────── --}}
        <div class="flex-1 min-w-0 bg-gray-50 overflow-auto">

            {{-- Execute --}}
            <div x-show="active === 'prompts'" class="p-5">
                @include('tasks.partials.prompts')
            </div>

            <div x-show="active === 'generator'" x-cloak class="p-5">
                @include('tasks.partials.prompt_generator')
            </div>

            <div x-show="active === 'logs'" x-cloak class="p-5">
                @include('tasks.partials.logs')
            </div>

            <div x-show="active === 'artifacts'" x-cloak class="p-5">
                @include('tasks.partials.artifacts')
            </div>

            <div x-show="active === 'review'" x-cloak class="p-5">
                @include('tasks.partials.reviews')
            </div>

            {{-- Reference --}}
            <div x-show="active === 'description'" x-cloak class="p-5">
                @include('tasks.partials.description')
            </div>

            <div x-show="active === 'context'" x-cloak class="p-5">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ __('ui.context') }}</h3>
                    @if($task->context)
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $task->context }}</p>
                    @else
                        <p class="text-sm text-gray-400 italic">No context provided.</p>
                    @endif
                </div>
            </div>

            <div x-show="active === 'instructions'" x-cloak class="p-5">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ __('ui.instructions') }}</h3>
                    @if($task->instructions)
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $task->instructions }}</p>
                    @else
                        <p class="text-sm text-gray-400 italic">No instructions provided.</p>
                    @endif
                </div>
            </div>

            <div x-show="active === 'acceptance'" x-cloak class="p-5">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ __('ui.acceptance_criteria') }}</h3>
                    @if($task->acceptance_criteria)
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $task->acceptance_criteria }}</p>
                    @else
                        <p class="text-sm text-gray-400 italic">No acceptance criteria provided.</p>
                    @endif
                </div>
            </div>

            {{-- Controls --}}
            <div x-show="active === 'controls'" x-cloak class="p-5">
                @include('tasks.partials.controls')
            </div>

        </div>
    </div>
</x-app-layout>
