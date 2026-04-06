{{-- Review Gate — submit review form (visible when InProgress or Review) + review history. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ __('ui.review_gate') }}</h3>

    @if($task->status === 'Review' || $task->status === 'InProgress')
        <form method="POST" action="{{ route('reviews.store', $task) }}"
              class="mb-4 p-3 border rounded-lg bg-gray-50">
            @csrf
            <div class="grid grid-cols-1 gap-2">
                {{-- Truth Audit Checklist --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('ui.truth_audit_checklist') }}</p>
                    @error('truth_audit')
                        <p class="text-xs text-red-600 mb-1">{{ $message }}</p>
                    @enderror
                    <div class="space-y-1">
                        @foreach([
                            'route_exists'   => __('ui.audit_route_exists'),
                            'ui_exists'      => __('ui.audit_ui_exists'),
                            'service_exists' => __('ui.audit_service_exists'),
                            'test_exists'    => __('ui.audit_test_exists'),
                        ] as $field => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="{{ $field }}" value="1"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    {{ old($field) ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ __('ui.truth_audit_hint') }}</p>
                </div>

                <div>
                    <x-input-label :value="__('ui.result')" />
                    <select name="result"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                        <option value="">{{ __('ui.select') }}</option>
                        <option value="pass" {{ old('result') === 'pass' ? 'selected' : '' }}>{{ __('ui.pass') }}</option>
                        <option value="changes_requested" {{ old('result') === 'changes_requested' ? 'selected' : '' }}>{{ __('ui.changes_requested') }}</option>
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('ui.note')" />
                    <x-text-input name="note" type="text" class="mt-1 block w-full text-sm"
                        :placeholder="__('ui.review_notes_placeholder')" value="{{ old('note') }}" required />
                </div>
                <div>
                    <x-primary-button>{{ __('ui.submit_review') }}</x-primary-button>
                </div>
            </div>
        </form>
    @endif

    @if($task->reviews->isEmpty())
        <p class="text-sm text-gray-400 italic">{{ __('ui.no_reviews_yet') }}</p>
    @else
        <div class="space-y-2">
            @foreach($task->reviews->sortByDesc('created_at') as $review)
                <div class="p-3 border rounded {{ $review->result === 'pass' ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50' }}">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold {{ $review->result === 'pass' ? 'text-green-700' : 'text-yellow-700' }}">
                            {{ $review->result === 'pass' ? __('ui.review_pass') : __('ui.review_changes') }}
                        </span>
                        <span class="text-xs text-gray-400">{{ $review->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{{ $review->note }}</p>
                    @if($review->result === 'pass')
                        <div class="flex gap-2 mt-1 flex-wrap">
                            @foreach([
                                'route_exists'   => __('ui.audit_route_exists'),
                                'ui_exists'      => __('ui.audit_ui_exists'),
                                'service_exists' => __('ui.audit_service_exists'),
                                'test_exists'    => __('ui.audit_test_exists'),
                            ] as $field => $label)
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $review->$field ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $review->$field ? '✓' : '✗' }} {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
