{{-- Task Execution Controls — placeholder panel for future worker/machine assignment and AI run. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Execution</h3>

    <div class="space-y-3">
        {{-- Assigned Worker placeholder --}}
        <div class="p-3 rounded-lg border border-dashed border-gray-300 bg-gray-50">
            <p class="text-xs font-medium text-gray-600 mb-1">Assigned Worker</p>
            <p class="text-xs text-gray-400 italic">No worker assigned</p>
            <button type="button" disabled
                class="mt-2 text-xs px-2.5 py-1 bg-gray-200 text-gray-400 rounded cursor-not-allowed">
                Assign Worker
            </button>
        </div>

        {{-- AI Execution placeholder --}}
        <div class="p-3 rounded-lg border border-dashed border-gray-300 bg-gray-50">
            <p class="text-xs font-medium text-gray-600 mb-1">AI Execution</p>
            <p class="text-xs text-gray-400 italic">Not yet implemented</p>
            <button type="button" disabled
                class="mt-2 text-xs px-2.5 py-1 bg-gray-200 text-gray-400 rounded cursor-not-allowed">
                Run Task
            </button>
        </div>
    </div>
</div>
