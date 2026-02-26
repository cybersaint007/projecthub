<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $task->title }}</h2>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('projects.show', $task->epic->project) }}" class="hover:underline">{{ $task->epic->project->name }}</a>
                    &rarr;
                    <a href="{{ route('epics.show', $task->epic) }}" class="hover:underline">{{ $task->epic->title }}</a>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('epics.kanban', $task->epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">Kanban</a>
                <a href="{{ route('tasks.edit', $task) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Edit</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Section 1: Description (editable) + meta --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Description</h3>
            <form method="POST" action="{{ route('tasks.description.update', $task) }}" class="mb-6">
                @csrf
                @method('PATCH')
                <textarea name="description" rows="4" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Task description...">{{ old('description', $task->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                <button type="submit" class="mt-2 px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">Update description</button>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t">
                <div>
                    <span class="text-xs text-gray-500 uppercase">Status</span>
                    @php
                        $statusColors = [
                            'TODO' => 'bg-gray-100 text-gray-700',
                            'Backlog' => 'bg-gray-100 text-gray-700',
                            'Ready' => 'bg-blue-100 text-blue-700',
                            'InProgress' => 'bg-yellow-100 text-yellow-700',
                            'Review' => 'bg-purple-100 text-purple-700',
                            'Done' => 'bg-green-100 text-green-700',
                        ];
                    @endphp
                    <div class="mt-1">
                        <form method="POST" action="{{ route('tasks.status', $task) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="text-sm border-0 rounded py-1 pr-6 {{ $statusColors[$task->status] ?? 'bg-gray-100 text-gray-700' }} focus:ring-indigo-500">
                                @foreach(\App\Models\Task::STATUSES as $s)
                                    <option value="{{ $s }}" {{ $task->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase">Agent</span>
                    <div class="mt-1 text-sm">{{ $task->agent }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase">Priority</span>
                    @php
                        $priorityLabels = \App\Models\Task::priorityOptions();
                        $priorityColors = [1 => 'text-gray-600', 3 => 'text-yellow-600', 5 => 'text-red-600'];
                    @endphp
                    <div class="mt-1 text-sm font-medium {{ $priorityColors[$task->priority] ?? 'text-gray-600' }}">{{ $priorityLabels[$task->priority] ?? 'Medium' }}</div>
                </div>
                <div>
                    <span class="text-xs text-gray-500 uppercase">Tags</span>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach($task->tags ?? [] as $tag)
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($task->context)
                <div class="mt-4 pt-4 border-t">
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Context</h4>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $task->context }}</p>
                </div>
            @endif

            @if($task->instructions)
                <div class="mt-4 pt-4 border-t">
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Instructions</h4>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $task->instructions }}</p>
                </div>
            @endif

            @if($task->acceptance_criteria)
                <div class="mt-4 pt-4 border-t">
                    <h4 class="text-sm font-medium text-gray-500 mb-1">Acceptance Criteria</h4>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $task->acceptance_criteria }}</p>
                </div>
            @endif
        </div>

        {{-- AI Prompts (saved prompts) --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{}">
            <h3 class="text-lg font-semibold mb-4">AI Prompts</h3>
            <p class="text-sm text-gray-500 mb-4">Saved prompts per agent and version. Add, edit, duplicate or delete.</p>

            <button type="button" @click="$dispatch('open-modal', 'add-prompt')" class="mb-4 px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Add Prompt</button>

            @if($task->taskPrompts->isEmpty())
                <p class="text-gray-500 text-sm">No prompts yet. Add one above or use the generator below.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left py-2 font-medium text-gray-500">Agent</th>
                                <th class="text-left py-2 font-medium text-gray-500">Format</th>
                                <th class="text-left py-2 font-medium text-gray-500">Title</th>
                                <th class="text-left py-2 font-medium text-gray-500">Version</th>
                                <th class="text-left py-2 font-medium text-gray-500">Updated At</th>
                                <th class="text-left py-2 font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($task->taskPrompts->sortByDesc('updated_at') as $p)
                                <tr>
                                    <td class="py-2">{{ $p->agent_type }}</td>
                                    <td class="py-2">{{ $p->format_type }}</td>
                                    <td class="py-2">{{ $p->title ?: '—' }}</td>
                                    <td class="py-2">{{ $p->version }}</td>
                                    <td class="py-2">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                                    <td class="py-2 flex flex-wrap gap-1">
                                        <a href="{{ route('prompts.show', [$task, $p]) }}" class="text-indigo-600 hover:underline">View</a>
                                        <a href="{{ route('prompts.edit', [$task, $p]) }}" class="text-indigo-600 hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('prompts.duplicate', [$task, $p]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:underline">Duplicate</button>
                                        </form>
                                        <form method="POST" action="{{ route('prompts.destroy', [$task, $p]) }}" class="inline" onsubmit="return confirm('Delete this prompt?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @php $addPromptErrors = $errors->has('agent_type') || $errors->has('format_type') || $errors->has('title') || $errors->has('content'); @endphp
        <x-modal name="add-prompt" :show="$addPromptErrors" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Add Prompt</h3>
                <form method="POST" action="{{ route('prompts.store', $task) }}" id="add-prompt-form">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="add_agent_type" value="Agent" />
                            <select id="add_agent_type" name="agent_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                @foreach(config('task_prompts.agent_types', \App\Models\TaskPrompt::AGENT_TYPES) as $a)
                                    <option value="{{ $a }}" {{ old('agent_type') === $a ? 'selected' : '' }}>{{ $a }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('agent_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="add_format_type" value="Format" />
                            <select id="add_format_type" name="format_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                @foreach(config('task_prompts.format_types', \App\Models\TaskPrompt::FORMAT_TYPES) as $f)
                                    <option value="{{ $f }}" {{ old('format_type', 'structured') === $f ? 'selected' : '' }}>{{ $f }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('format_type')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="add_title" value="Title (optional)" />
                            <x-text-input id="add_title" name="title" type="text" class="block w-full" :value="old('title')" />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="add_content" value="Content" />
                            <textarea id="add_content" name="content" rows="12" class="mt-1 block w-full font-mono text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required placeholder="Paste or type your prompt...">{{ old('content') }}</textarea>
                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <x-primary-button>Add Prompt</x-primary-button>
                        <button type="button" @click="$dispatch('close-modal', 'add-prompt')" class="px-4 py-2 border rounded hover:bg-gray-50">Cancel</button>
                    </div>
                </form>
            </div>
        </x-modal>

        {{-- Section 3: Work Logs timeline --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Work Logs</h3>

            <form method="POST" action="{{ route('task-logs.store', $task) }}" class="mb-6 p-4 border rounded-lg bg-gray-50">
                @csrf
                <input type="hidden" name="log_type" value="manual" />
                <div class="space-y-3">
                    <textarea name="content" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add a work log entry..." required></textarea>
                    <x-input-error :messages="$errors->get('content')" class="mt-1" />
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Add Log</button>
                </div>
            </form>

            @if($task->taskLogs->isEmpty())
                <p class="text-gray-500 text-sm">No work logs yet. Add one above.</p>
            @else
                <div class="space-y-0 border-l-2 border-gray-200 pl-4">
                    @foreach($task->taskLogs as $log)
                        <div class="relative pb-6 last:pb-0" x-data="{ editing: false }">
                            <span class="absolute -left-4 top-1.5 h-2 w-2 rounded-full {{ $log->log_type === 'manual' ? 'bg-indigo-500' : ($log->log_type === 'ai' ? 'bg-purple-500' : 'bg-gray-400') }}" aria-hidden="true"></span>
                            <div class="ml-2">
                                <div class="flex items-center gap-2 text-xs text-gray-500 mb-0.5">
                                    <span class="px-1.5 py-0.5 rounded {{ $log->log_type === 'manual' ? 'bg-indigo-100 text-indigo-700' : ($log->log_type === 'ai' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600') }}">{{ $log->log_type }}</span>
                                    <span>{{ $log->created_at->format('M j, Y H:i') }}</span>
                                    @if($log->user)
                                        <span>{{ $log->user->name }}</span>
                                    @endif
                                    <button type="button" @click="editing = !editing" class="ml-auto text-[11px] text-indigo-600 hover:underline">Edit</button>
                                </div>
                                <div x-show="!editing">
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $log->content }}</p>
                                </div>
                                <div x-show="editing" class="mt-2">
                                    <form method="POST" action="{{ route('task-logs.update', $log) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="content" rows="3" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('content', $log->content) }}</textarea>
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Save</button>
                                            <button type="button" @click="editing = false" class="px-3 py-1.5 text-xs border rounded hover:bg-gray-50">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- AI Prompt Generator (legacy quick preview) --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ tab: 'claude' }">
            <h3 class="text-lg font-semibold mb-4">AI Prompt Generator</h3>

            <div class="flex flex-wrap gap-2 mb-4">
                <button @click="tab = 'claude'" :class="tab === 'claude' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-2 text-sm rounded">Claude Code</button>
                <button @click="tab = 'cursor'" :class="tab === 'cursor' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-2 text-sm rounded">Cursor 2</button>
                <button @click="tab = 'deepseek'" :class="tab === 'deepseek' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-2 text-sm rounded">Deepseek</button>
                <button @click="tab = 'openclaw'" :class="tab === 'openclaw' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'" class="px-4 py-2 text-sm rounded">OpenClaw</button>
            </div>

            {{-- Claude Code Prompt --}}
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
                <textarea id="claude-prompt" readonly rows="18" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $claudePrompt }}</textarea>
                <button onclick="navigator.clipboard.writeText(document.getElementById('claude-prompt').value).then(() => this.textContent = 'Copied!').catch(() => {}); setTimeout(() => this.textContent = 'Copy to Clipboard', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Copy to Clipboard</button>
            </div>

            {{-- Cursor 2 Prompt --}}
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
                <textarea id="cursor-prompt" readonly rows="18" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $cursorPrompt }}</textarea>
                <button onclick="navigator.clipboard.writeText(document.getElementById('cursor-prompt').value).then(() => this.textContent = 'Copied!').catch(() => {}); setTimeout(() => this.textContent = 'Copy to Clipboard', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Copy to Clipboard</button>
            </div>

            {{-- Deepseek Prompt --}}
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
                <textarea id="deepseek-prompt" readonly rows="18" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $deepseekPrompt }}</textarea>
                <button onclick="navigator.clipboard.writeText(document.getElementById('deepseek-prompt').value).then(() => this.textContent = 'Copied!').catch(() => {}); setTimeout(() => this.textContent = 'Copy to Clipboard', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Copy to Clipboard</button>
            </div>

            {{-- OpenClaw Prompt --}}
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
                <textarea id="openclaw-prompt" readonly rows="18" class="w-full font-mono text-sm border-gray-300 rounded-md bg-gray-50 focus:ring-0">{{ $openclawPrompt }}</textarea>
                <button onclick="navigator.clipboard.writeText(document.getElementById('openclaw-prompt').value).then(() => this.textContent = 'Copied!').catch(() => {}); setTimeout(() => this.textContent = 'Copy to Clipboard', 2000)" class="mt-2 px-4 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-900">Copy to Clipboard</button>
            </div>
        </div>

        {{-- Artifacts --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Artifacts</h3>

            {{-- Add artifact form --}}
            <form method="POST" action="{{ route('artifacts.store', $task) }}" class="mb-4 p-4 border rounded-lg bg-gray-50">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <select name="type" class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Type...</option>
                            @foreach(\App\Models\TaskArtifact::TYPES as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-text-input name="value" type="text" class="block w-full text-sm" placeholder="Value (path, URL, etc.)" required />
                    </div>
                    <div class="flex gap-2">
                        <x-text-input name="note" type="text" class="block w-full text-sm" placeholder="Note (optional)" />
                        <x-primary-button class="whitespace-nowrap">Add</x-primary-button>
                    </div>
                </div>
            </form>

            @if($task->artifacts->isEmpty())
                <p class="text-gray-500 text-sm">No artifacts yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($task->artifacts as $artifact)
                        <div class="flex items-center justify-between p-3 border rounded">
                            <div>
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded mr-2">{{ $artifact->type }}</span>
                                <span class="text-sm">{{ $artifact->value }}</span>
                                @if($artifact->note)
                                    <span class="text-xs text-gray-400 ml-2">{{ $artifact->note }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('artifacts.destroy', $artifact) }}" onsubmit="return confirm('Remove artifact?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 text-sm hover:underline">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Review Gate --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Review Gate</h3>

            @if($task->status === 'Review' || $task->status === 'InProgress')
                <form method="POST" action="{{ route('reviews.store', $task) }}" class="mb-4 p-4 border rounded-lg bg-gray-50">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div>
                            <x-input-label value="Result" />
                            <select name="result" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select...</option>
                                <option value="pass">Pass</option>
                                <option value="changes_requested">Changes Requested</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label value="Note" />
                            <x-text-input name="note" type="text" class="mt-1 block w-full text-sm" placeholder="Review notes..." required />
                        </div>
                        <x-primary-button>Submit Review</x-primary-button>
                    </div>
                </form>
            @endif

            @if($task->reviews->isEmpty())
                <p class="text-gray-500 text-sm">No reviews yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($task->reviews->sortByDesc('created_at') as $review)
                        <div class="p-3 border rounded {{ $review->result === 'pass' ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50' }}">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium {{ $review->result === 'pass' ? 'text-green-700' : 'text-yellow-700' }}">
                                    {{ $review->result === 'pass' ? 'PASS' : 'CHANGES REQUESTED' }}
                                </span>
                                <span class="text-xs text-gray-400">{{ $review->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $review->note }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
