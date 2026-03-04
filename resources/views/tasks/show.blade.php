{{--
  Task Detail — "Task Control Center"
  ────────────────────────────────────────────────────────────────────────────
  Layout: 2-column responsive grid (lg: left 2/3, right 1/3)

  Left column  (lg:col-span-2 — Task Definition)
    · partials/info.blade.php              Status / Agent / Priority / Tags
    · partials/description.blade.php       Description (view-first, Alpine edit toggle)
    · partials/definition_panels.blade.php Context / Instructions / Acceptance Criteria (collapsible)
    · partials/prompt_generator.blade.php  AI Prompt Generator (legacy tabbed view, collapsible)

  Right column (Task Execution)
    · partials/controls.blade.php          Worker placeholder + AI execution placeholder
    · partials/prompts.blade.php           Saved AI Prompts (cards) + Add Prompt modal
    · partials/logs.blade.php              Work Logs timeline + add form
    · partials/artifacts.blade.php         Task Artifacts + add / upload forms
    · partials/reviews.blade.php           Review Gate
--}}
<x-app-layout>
    <x-slot name="header">
        @include('tasks.partials.header')
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Left: Task Definition ──────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-4">
            @include('tasks.partials.info')
            @include('tasks.partials.description')
            @include('tasks.partials.definition_panels')
            @include('tasks.partials.prompt_generator')
        </div>

        {{-- ── Right: Task Execution ──────────────────────────────────────── --}}
        <div class="space-y-4">
            @include('tasks.partials.controls')
            @include('tasks.partials.prompts')
            @include('tasks.partials.logs')
            @include('tasks.partials.artifacts')
            @include('tasks.partials.reviews')
        </div>

    </div>
</x-app-layout>
