<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.files') }}: {{ $project->name }}</h2>
            </div>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_project') }}</a>
        </div>
    </x-slot>

    {{-- Upload Form --}}
    <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium mb-4">{{ __('ui.upload_file') }}</h3>
        <form method="POST" action="{{ route('project-files.store', $project) }}" enctype="multipart/form-data">
            @csrf
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-[200px]">
                    <x-input-label for="file" :value="__('ui.file_max_size')" />
                    <input id="file" name="file" type="file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required />
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-input-label for="note" :value="__('ui.note_optional')" />
                    <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" />
                </div>
                <x-primary-button>{{ __('ui.upload') }}</x-primary-button>
            </div>
            <p class="mt-2 text-xs text-gray-400">{{ __('ui.allowed_file_types') }}</p>
        </form>
    </div>

    {{-- Files List --}}
    <div class="bg-white shadow-sm sm:rounded-lg">
        @if($files->isEmpty())
            <p class="p-6 text-gray-500">{{ __('ui.no_files_yet') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.size') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.uploaded_by') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.note') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($files as $f)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium">{{ $f->original_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ number_format($f->size / 1024, 1) }} KB</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $f->uploader?->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $f->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($f->note, 40) }}</td>
                            <td class="px-6 py-4 text-right text-sm space-x-2">
                                <a href="{{ route('project-files.download', $f) }}" class="text-indigo-600 hover:underline">{{ __('ui.download') }}</a>
                                @if(Auth::user()->isAdmin() || $f->uploader_user_id === Auth::id())
                                    <form method="POST" action="{{ route('project-files.destroy', $f) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_delete_file') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('ui.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
