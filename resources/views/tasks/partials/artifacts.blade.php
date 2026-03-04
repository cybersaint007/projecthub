{{-- Task Artifacts — link/metadata add form, file upload form, and artifact list. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ __('ui.artifacts') }}</h3>

    {{-- Add artifact (link/metadata) --}}
    <form method="POST" action="{{ route('artifacts.store', $task) }}" class="mb-3 p-3 border rounded-lg bg-gray-50">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
                <select name="type"
                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required>
                    <option value="">{{ __('ui.type_placeholder') }}</option>
                    @foreach(\App\Models\TaskArtifact::TYPES as $t)
                        @if($t !== 'file_path')
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <x-text-input name="value" type="text" class="block w-full text-sm"
                    :placeholder="__('ui.value_placeholder')" required />
            </div>
            <div class="flex gap-2">
                <x-text-input name="note" type="text" class="block w-full text-sm"
                    :placeholder="__('ui.note_optional')" />
                <x-primary-button class="whitespace-nowrap">{{ __('ui.add') }}</x-primary-button>
            </div>
        </div>
    </form>

    {{-- File upload --}}
    <form method="POST" action="{{ route('artifacts.store-file', $task) }}" enctype="multipart/form-data"
          class="mb-3 p-3 border rounded-lg bg-gray-50">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
            <div>
                <x-input-label for="artifact-file" :value="__('ui.file_max_size')" />
                <input id="artifact-file" name="file" type="file"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    required />
            </div>
            <div>
                <x-text-input name="note" type="text" class="block w-full text-sm"
                    :placeholder="__('ui.note_optional')" />
            </div>
            <div>
                <x-primary-button class="whitespace-nowrap">{{ __('ui.upload') }}</x-primary-button>
            </div>
        </div>
    </form>

    {{-- Artifact list --}}
    @if($task->artifacts->isEmpty())
        <p class="text-sm text-gray-400 italic">{{ __('ui.no_artifacts_yet') }}</p>
    @else
        <div class="space-y-2">
            @foreach($task->artifacts as $artifact)
                <div class="flex items-center justify-between p-2.5 border rounded bg-white text-sm">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded flex-shrink-0">{{ $artifact->type }}</span>
                        @if($artifact->type === 'file_path' && $artifact->projectFile)
                            <a href="{{ route('project-files.download', $artifact->projectFile) }}"
                               class="text-indigo-600 hover:underline truncate">{{ $artifact->projectFile->original_name }}</a>
                            <span class="text-xs text-gray-400 flex-shrink-0">({{ number_format($artifact->projectFile->size / 1024, 1) }} KB)</span>
                        @elseif($artifact->type === 'url' || $artifact->type === 'pr')
                            <a href="{{ $artifact->value }}" target="_blank"
                               class="text-indigo-600 hover:underline truncate">{{ $artifact->value }}</a>
                        @else
                            <span class="truncate">{{ $artifact->value }}</span>
                        @endif
                        @if($artifact->note)
                            <span class="text-xs text-gray-400 flex-shrink-0">{{ $artifact->note }}</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('artifacts.destroy', $artifact) }}"
                          onsubmit="return confirm('{{ __('ui.confirm_remove_artifact') }}')"
                          class="flex-shrink-0 ml-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-500 text-xs hover:underline">{{ __('ui.remove') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
