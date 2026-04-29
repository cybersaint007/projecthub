{{-- AI Prompt Generator — tabbed, always expanded (shown via menu panel). --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5" x-data="{ tab: 'claude' }">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ __('ui.ai_prompt_generator') }}</h3>

    <div class="flex flex-wrap gap-2 mb-4">
        <button @click="tab = 'claude'"    :class="tab === 'claude'    ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">Claude Code</button>
        <button @click="tab = 'cursor'"    :class="tab === 'cursor'    ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">Cursor 2</button>
        <button @click="tab = 'deepseek'"  :class="tab === 'deepseek'  ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">Deepseek</button>
        <button @click="tab = 'openclaw'"  :class="tab === 'openclaw'  ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">OpenClaw</button>
        <button @click="tab = 'ollama'"    :class="tab === 'ollama'    ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">Ollama</button>
        <button @click="tab = 'hermes'"    :class="tab === 'hermes'    ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-3 py-1.5 text-sm rounded">Hermes</button>
    </div>

    {{-- Claude Code --}}
    <div x-show="tab === 'claude'">
        @php
        $claudePrompt = "## Task: {$task->title}\n\n";
        $claudePrompt .= "### Background / Context\n";
        $claudePrompt .= ($task->context ?: 'N/A') . "\n\n";
        $claudePrompt .= "### Goal / Instructions\n";
        $claudePrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $claudePrompt .= "### Acceptance Criteria\n";
        $claudePrompt .= ($task->acceptance_criteria ?: 'N/A') . "\n\n";
        $claudePrompt .= "### Constraints\n";
        $claudePrompt .= "- Keep it MVP. Do not add extra features beyond what is specified.\n";
        $claudePrompt .= "- Follow existing project conventions and patterns.\n";
        $claudePrompt .= "- Avoid over-engineering or premature abstractions.\n\n";
        $claudePrompt .= "### Deliverables\n";
        $claudePrompt .= "- All modified/created files (controllers, models, views, migrations, routes)\n";
        $claudePrompt .= "- Seed data if applicable\n";
        $claudePrompt .= "- Update README if new setup steps are needed\n";
        $claudePrompt .= "- Verify with: php artisan serve + manual testing";
        @endphp
        <textarea id="claude-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $claudePrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('claude-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>

    {{-- Cursor 2 --}}
    <div x-show="tab === 'cursor'" x-cloak>
        @php
        $cursorPrompt = "Task: {$task->title}\n\n";
        $cursorPrompt .= "Context: " . ($task->context ?: 'N/A') . "\n\n";
        $cursorPrompt .= "Instructions:\n";
        $cursorPrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $cursorPrompt .= "TODO Checklist:\n";
        if ($task->acceptance_criteria) {
            foreach (explode("\n", $task->acceptance_criteria) as $line) {
                $line = trim($line);
                if ($line) $cursorPrompt .= "- [ ] {$line}\n";
            }
        } else {
            $cursorPrompt .= "- [ ] Implement the task as described\n";
            $cursorPrompt .= "- [ ] Verify it works\n";
        }
        $cursorPrompt .= "\nFiles to modify: Search the project for relevant files before making changes.\n";
        $cursorPrompt .= "Testing: php artisan serve, then manually verify the affected routes.\n";
        $cursorPrompt .= "Scope: Only make the changes described above. Do not expand scope or add extra features.";
        @endphp
        <textarea id="cursor-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $cursorPrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('cursor-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>

    {{-- Deepseek --}}
    <div x-show="tab === 'deepseek'" x-cloak>
        @php
        $deepseekPrompt = "## Task: {$task->title}\n\n";
        $deepseekPrompt .= "### Background / Context\n";
        $deepseekPrompt .= ($task->context ?: 'N/A') . "\n\n";
        $deepseekPrompt .= "### Goal / Instructions\n";
        $deepseekPrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $deepseekPrompt .= "### Acceptance Criteria\n";
        $deepseekPrompt .= ($task->acceptance_criteria ?: 'N/A') . "\n\n";
        $deepseekPrompt .= "### Constraints\n";
        $deepseekPrompt .= "- Keep it MVP. Do not add extra features beyond what is specified.\n";
        $deepseekPrompt .= "- Follow existing project conventions and patterns.\n";
        $deepseekPrompt .= "- Verify with: php artisan serve + manual testing.";
        @endphp
        <textarea id="deepseek-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $deepseekPrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('deepseek-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>

    {{-- OpenClaw --}}
    <div x-show="tab === 'openclaw'" x-cloak>
        @php
        $openclawPrompt = "## Task: {$task->title}\n\n";
        $openclawPrompt .= "### Background / Context\n";
        $openclawPrompt .= ($task->context ?: 'N/A') . "\n\n";
        $openclawPrompt .= "### Goal / Instructions\n";
        $openclawPrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $openclawPrompt .= "### Acceptance Criteria\n";
        $openclawPrompt .= ($task->acceptance_criteria ?: 'N/A') . "\n\n";
        $openclawPrompt .= "### Constraints\n";
        $openclawPrompt .= "- Keep it MVP. Do not add extra features beyond what is specified.\n";
        $openclawPrompt .= "- Follow existing project conventions and patterns.\n";
        $openclawPrompt .= "- Verify with: php artisan serve + manual testing.";
        @endphp
        <textarea id="openclaw-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $openclawPrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('openclaw-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>

    {{-- Ollama --}}
    <div x-show="tab === 'ollama'" x-cloak>
        @php
        $ollamaPrompt = "## Task: {$task->title}\n\n";
        $ollamaPrompt .= "### Background / Context\n";
        $ollamaPrompt .= ($task->context ?: 'N/A') . "\n\n";
        $ollamaPrompt .= "### Goal / Instructions\n";
        $ollamaPrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $ollamaPrompt .= "### Acceptance Criteria\n";
        $ollamaPrompt .= ($task->acceptance_criteria ?: 'N/A') . "\n\n";
        $ollamaPrompt .= "### Constraints\n";
        $ollamaPrompt .= "- Keep it MVP. Do not add extra features beyond what is specified.\n";
        $ollamaPrompt .= "- Follow existing project conventions and patterns.\n";
        $ollamaPrompt .= "- Verify with: php artisan serve + manual testing.";
        @endphp
        <textarea id="ollama-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $ollamaPrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('ollama-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>

    {{-- Hermes --}}
    <div x-show="tab === 'hermes'" x-cloak>
        @php
        $hermesPrompt = "### Instruction\n";
        $hermesPrompt .= "You are a skilled software engineer. Complete the following task precisely as described.\n\n";
        $hermesPrompt .= "### Task\n";
        $hermesPrompt .= "{$task->title}\n\n";
        $hermesPrompt .= "### Context\n";
        $hermesPrompt .= ($task->context ?: 'N/A') . "\n\n";
        $hermesPrompt .= "### Steps\n";
        $hermesPrompt .= ($task->instructions ?: 'N/A') . "\n\n";
        $hermesPrompt .= "### Acceptance Criteria\n";
        $hermesPrompt .= ($task->acceptance_criteria ?: 'N/A') . "\n\n";
        $hermesPrompt .= "### Response\n";
        $hermesPrompt .= "Implement the task. Keep changes minimal and follow existing project conventions.";
        @endphp
        <textarea id="hermes-prompt" readonly rows="16" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $hermesPrompt }}</textarea>
        <button onclick="navigator.clipboard.writeText(document.getElementById('hermes-prompt').value).then(() => this.textContent = '{{ __('ui.copied') }}').catch(() => {}); setTimeout(() => this.textContent = '{{ __('ui.copy_to_clipboard') }}', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">{{ __('ui.copy_to_clipboard') }}</button>
    </div>
</div>
