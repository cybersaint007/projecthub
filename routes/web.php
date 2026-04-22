<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\TaskArtifactController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskLogController;
use App\Http\Controllers\TaskPromptController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\EpicBacklogController;
use App\Http\Controllers\AgentDashboardController;
use App\Http\Controllers\TaskReviewController;
use App\Http\Controllers\AgentTokenController;
use App\Http\Controllers\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Force password change
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);
    Route::get('/projects/{project}/access', [ProjectController::class, 'access'])->name('projects.access');
    Route::post('/projects/{project}/access', [ProjectController::class, 'addAccessUser'])->name('projects.access.add');
    Route::put('/projects/{project}/access/{user}', [ProjectController::class, 'updateAccessUser'])->name('projects.access.update');
    Route::delete('/projects/{project}/access/{user}', [ProjectController::class, 'removeAccessUser'])->name('projects.access.remove');
    Route::put('/projects/{project}/owner', [ProjectController::class, 'updateOwner'])->name('projects.owner.update');
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::get('/projects/{project}/webhooks', [WebhookEndpointController::class, 'index'])->name('projects.webhooks');
    Route::post('/projects/{project}/webhooks', [WebhookEndpointController::class, 'store'])->name('projects.webhooks.store');
    Route::delete('/projects/{project}/webhooks/{webhook}', [WebhookEndpointController::class, 'destroy'])->name('projects.webhooks.destroy');

    Route::get('/projects/{project}/agent-tokens', [AgentTokenController::class, 'index'])->name('projects.agent-tokens');
    Route::post('/projects/{project}/agent-tokens', [AgentTokenController::class, 'store'])->name('projects.agent-tokens.store');
    Route::delete('/projects/{project}/agent-tokens/{agentToken}', [AgentTokenController::class, 'destroy'])->name('projects.agent-tokens.destroy');

    // Epics
    Route::post('/projects/{project}/epics/reorder', [ProjectController::class, 'reorderEpics'])->name('projects.epics.reorder');
    Route::post('/projects/{project}/tasks/reorder', [ProjectController::class, 'reorderTasks'])->name('projects.tasks.reorder');
    Route::get('/projects/{project}/epics/create', [EpicController::class, 'create'])->name('epics.create');
    Route::post('/projects/{project}/epics', [EpicController::class, 'store'])->name('epics.store');
    Route::get('/epics/{epic}', [EpicController::class, 'show'])->name('epics.show');
    Route::get('/epics/{epic}/edit', [EpicController::class, 'edit'])->name('epics.edit');
    Route::put('/epics/{epic}', [EpicController::class, 'update'])->name('epics.update');
    Route::delete('/epics/{epic}', [EpicController::class, 'destroy'])->name('epics.destroy');
    Route::post('/epics/{id}/restore', [EpicController::class, 'restore'])->name('epics.restore');
    Route::get('/epics/{epic}/kanban', [EpicController::class, 'kanban'])->name('epics.kanban');
    Route::patch('/epics/{epic}/bulk-agent', [EpicController::class, 'bulkUpdateAgent'])->name('epics.bulk-agent');

    // Tasks
    Route::get('/epics/{epic}/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/epics/{epic}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{id}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
    Route::patch('/tasks/{task}/description', [TaskController::class, 'updateDescription'])->name('tasks.description.update');
    Route::post('/tasks/{task}/logs', [TaskLogController::class, 'store'])->name('task-logs.store');
    Route::patch('/task-logs/{taskLog}', [TaskLogController::class, 'update'])->name('task-logs.update');
    Route::delete('/task-logs/{taskLog}', [TaskLogController::class, 'destroy'])->name('task-logs.destroy');

    // Task Artifacts
    Route::post('/tasks/{task}/artifacts', [TaskArtifactController::class, 'store'])->name('artifacts.store');
    Route::post('/tasks/{task}/artifacts/file', [TaskArtifactController::class, 'storeFile'])->name('artifacts.store-file');
    Route::delete('/artifacts/{artifact}', [TaskArtifactController::class, 'destroy'])->name('artifacts.destroy');

    // Task Prompts (AI Prompts)
    Route::post('/tasks/{task}/prompts', [TaskPromptController::class, 'store'])->name('prompts.store');
    Route::get('/tasks/{task}/prompts/{task_prompt}', [TaskPromptController::class, 'show'])->name('prompts.show')->scopeBindings();
    Route::get('/tasks/{task}/prompts/{task_prompt}/edit', [TaskPromptController::class, 'edit'])->name('prompts.edit')->scopeBindings();
    Route::put('/tasks/{task}/prompts/{task_prompt}', [TaskPromptController::class, 'update'])->name('prompts.update')->scopeBindings();
    Route::post('/tasks/{task}/prompts/{task_prompt}/duplicate', [TaskPromptController::class, 'duplicate'])->name('prompts.duplicate')->scopeBindings();
    Route::delete('/tasks/{task}/prompts/{task_prompt}', [TaskPromptController::class, 'destroy'])->name('prompts.destroy')->scopeBindings();

    // Task Reviews
    Route::post('/tasks/{task}/reviews', [TaskReviewController::class, 'store'])->name('reviews.store');

    // Backlog Export/Import (V3 canonical) — project level
    Route::get('/projects/{project}/backlog/export-v3', [BacklogController::class, 'exportV3Json'])->name('backlog.export-v3');
    Route::get('/projects/{project}/backlog/import-v3', [BacklogController::class, 'importV3Form'])->name('backlog.import-v3');
    Route::post('/projects/{project}/backlog/import-v3', [BacklogController::class, 'importV3Apply'])->name('backlog.import-v3.apply');
    Route::get('/import-v3', [BacklogController::class, 'importV3GlobalForm'])->name('backlog.import-v3.global');
    Route::post('/import-v3', [BacklogController::class, 'importV3GlobalApply'])->name('backlog.import-v3.global.apply');

    // Backlog Export/Import (V3 canonical) — epic level
    Route::get('/epics/{epic}/export-v3', [EpicBacklogController::class, 'exportV3Json'])->name('backlog.epic.export-v3');
    Route::get('/projects/{project}/epics/import-v3', [EpicBacklogController::class, 'importEpicForm'])->name('backlog.epic.import-v3');
    Route::post('/projects/{project}/epics/import-v3', [EpicBacklogController::class, 'importEpicApply'])->name('backlog.epic.import-v3.apply');
    Route::get('/epics/{epic}/import-v3', [EpicBacklogController::class, 'importTasksForm'])->name('backlog.epic.import-tasks');
    Route::post('/epics/{epic}/import-v3', [EpicBacklogController::class, 'importTasksApply'])->name('backlog.epic.import-tasks.apply');

    // Project Files
    Route::get('/projects/{project}/files', [ProjectFileController::class, 'index'])->name('project-files.index');
    Route::post('/projects/{project}/files', [ProjectFileController::class, 'store'])->name('project-files.store');
    Route::get('/files/{file}/download', [ProjectFileController::class, 'download'])->name('project-files.download');
    Route::delete('/files/{file}', [ProjectFileController::class, 'destroy'])->name('project-files.destroy');

    // Admin routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class)->except(['destroy']);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/sync-projects', [AdminUserController::class, 'syncProjects'])->name('users.sync-projects');
    });

    // Agent dashboard (admin-only, enforced in controller)
    Route::middleware('admin')->get('/agent/dashboard', [AgentDashboardController::class, 'index'])->name('agent.dashboard');

});

require __DIR__.'/auth.php';
