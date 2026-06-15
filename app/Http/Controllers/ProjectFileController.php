<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectFileController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $files = $project->files()->with('uploader')->latest()->get();

        return view('projects.files', compact('project', 'files'));
    }

    public function store(Request $request, Project $project)
    {
        $this->authorizeProject($request->user(), $project);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (ProjectFile::MAX_SIZE / 1024),
                'extensions:' . implode(',', ProjectFile::ALLOWED_EXTENSIONS),
            ],
            'note' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $now = now();
        $path = sprintf('projects/%d/%s/%s', $project->id, $now->format('Y'), $now->format('m'));
        $storedPath = $file->store($path, 'projecthub_private');

        $project->files()->create([
            'uploader_user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'note' => $request->note,
        ]);

        return back()->with('status', __('ui.flash_file_uploaded'));
    }

    public function download(Request $request, ProjectFile $file)
    {
        $this->authorizeProject($request->user(), $file->project);

        return Storage::disk('projecthub_private')->download(
            $file->stored_path,
            $file->original_name
        );
    }

    public function destroy(Request $request, ProjectFile $file)
    {
        $this->authorizeProject($request->user(), $file->project);

        // Admin can delete all; user can only delete own uploads
        if (!$request->user()->isAdmin() && $file->uploader_user_id !== $request->user()->id) {
            abort(403, __('ui.error_only_delete_own_files'));
        }

        Storage::disk('projecthub_private')->delete($file->stored_path);
        $file->delete();

        return back()->with('status', __('ui.flash_file_deleted'));
    }

    private function authorizeProject($user, Project $project): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403, __('ui.error_not_assigned_project'));
        }
    }
}
