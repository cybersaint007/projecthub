<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskArtifactController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeTask($request->user(), $task);

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', TaskArtifact::TYPES),
            'value' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $task->artifacts()->create($data);

        return back()->with('status', __('ui.flash_artifact_added'));
    }

    public function storeFile(Request $request, Task $task)
    {
        $this->authorizeTask($request->user(), $task);

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
        $project = $task->epic->project;
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

        $task->artifacts()->create([
            'type' => 'file_path',
            'value' => $storedPath,
            'note' => $request->note ?: $file->getClientOriginalName(),
        ]);

        return back()->with('status', __('ui.flash_file_uploaded_task'));
    }

    public function destroy(Request $request, TaskArtifact $artifact)
    {
        $this->authorizeTask($request->user(), $artifact->task);

        $artifact->delete();

        return back()->with('status', __('ui.flash_artifact_removed'));
    }

    private function authorizeTask($user, Task $task): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $project = $task->epic->project;
        if (!$user->projects()->where('projects.id', $project->id)->exists()) {
            abort(403);
        }
    }
}
