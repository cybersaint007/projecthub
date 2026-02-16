<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\Import\BacklogImportController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\TaskArtifactController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskReviewController;
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
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    // Epics
    Route::get('/projects/{project}/epics/create', [EpicController::class, 'create'])->name('epics.create');
    Route::post('/projects/{project}/epics', [EpicController::class, 'store'])->name('epics.store');
    Route::get('/epics/{epic}', [EpicController::class, 'show'])->name('epics.show');
    Route::get('/epics/{epic}/edit', [EpicController::class, 'edit'])->name('epics.edit');
    Route::put('/epics/{epic}', [EpicController::class, 'update'])->name('epics.update');
    Route::delete('/epics/{epic}', [EpicController::class, 'destroy'])->name('epics.destroy');
    Route::get('/epics/{epic}/kanban', [EpicController::class, 'kanban'])->name('epics.kanban');

    // Tasks
    Route::get('/epics/{epic}/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/epics/{epic}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Task Artifacts
    Route::post('/tasks/{task}/artifacts', [TaskArtifactController::class, 'store'])->name('artifacts.store');
    Route::delete('/artifacts/{artifact}', [TaskArtifactController::class, 'destroy'])->name('artifacts.destroy');

    // Task Reviews
    Route::post('/tasks/{task}/reviews', [TaskReviewController::class, 'store'])->name('reviews.store');

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

    // Import routes (admin only)
    Route::middleware('admin')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/backlog', [BacklogImportController::class, 'show'])->name('backlog.show');
        Route::post('/backlog', [BacklogImportController::class, 'store'])->name('backlog.store');
    });
});

require __DIR__.'/auth.php';
