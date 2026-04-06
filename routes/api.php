<?php

use App\Http\Controllers\Api\AgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->middleware('agent.token')->group(function () {
    // Get project info (name, code)
    Route::get('/projects/{project}', [AgentController::class, 'projectInfo']);

    // Find next eligible task in a project
    Route::get('/projects/{project}/tasks/next', [AgentController::class, 'nextTask']);

    // Claim a task for execution
    Route::post('/tasks/{task}/claim', [AgentController::class, 'claim']);

    // Get the full task bundle (project + epic + task + prompt)
    Route::get('/tasks/{task}/bundle', [AgentController::class, 'bundle']);

    // Post execution logs
    Route::post('/tasks/{task}/logs', [AgentController::class, 'storeLog']);

    // Upload artifacts
    Route::post('/tasks/{task}/artifacts', [AgentController::class, 'storeArtifact']);

    // Update task status
    Route::patch('/tasks/{task}/status', [AgentController::class, 'updateStatus']);
});
