<?php

namespace Database\Seeders;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\TaskReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@projecthub.local',
            'password' => 'admin1234',
            'is_admin' => true,
            'force_password_reset' => false,
            'email_verified_at' => now(),
        ]);

        // 2. Demo users
        $demoUser = User::create([
            'name' => 'Demo User',
            'email' => 'demo@projecthub.local',
            'password' => 'demo1234',
            'is_admin' => false,
            'force_password_reset' => false, // for easy demo
            'email_verified_at' => now(),
        ]);

        $newUser = User::create([
            'name' => 'New User',
            'email' => 'newuser@projecthub.local',
            'password' => 'newuser1234',
            'is_admin' => false,
            'force_password_reset' => true, // will be forced to change password
            'email_verified_at' => now(),
        ]);

        // 3. Demo Project
        $project = Project::create([
            'name' => 'ProjectHub MVP',
            'description' => 'A minimal project management system with task tracking and AI prompt generation.',
        ]);

        // Assign demo user to project
        $project->users()->attach([$demoUser->id]);

        // 4. Epic
        $epic = Epic::create([
            'project_id' => $project->id,
            'title' => 'Core Features',
            'description' => 'Implement the core feature set for the MVP release.',
            'milestone_tag' => 'v0.1',
        ]);

        // 5. Tasks
        $task1 = Task::create([
            'epic_id' => $epic->id,
            'title' => 'User authentication system',
            'description' => 'Implement login/logout with admin-managed accounts.',
            'status' => 'Done',
            'agent' => 'claude_code',
            'priority' => 'high',
            'tags' => ['auth', 'backend'],
            'context' => 'The system requires admin-managed user accounts. No self-registration. Users must change password on first login.',
            'instructions' => "Install Laravel Breeze (Blade). Remove registration routes. Add is_admin and force_password_reset fields to users. Create middleware to enforce password change on first login.",
            'acceptance_criteria' => "Login page works\nRegistration is disabled\nAdmin can create users with generated passwords\nFirst login forces password change\nPassword change form validates current password",
        ]);

        $task2 = Task::create([
            'epic_id' => $epic->id,
            'title' => 'Project CRUD and assignment',
            'description' => 'Create project management with user assignment.',
            'status' => 'InProgress',
            'agent' => 'claude_code',
            'priority' => 'high',
            'tags' => ['backend', 'crud'],
            'context' => 'Projects are the top-level entity. Admin can create projects and assign users. Regular users only see assigned projects.',
            'instructions' => "Create Project model, migration, controller. Build project_user pivot. Admin can assign users to projects via checkboxes.",
            'acceptance_criteria' => "CRUD for projects works\nAdmin can assign users\nRegular users only see assigned projects",
        ]);

        $task3 = Task::create([
            'epic_id' => $epic->id,
            'title' => 'AI Prompt Generator',
            'description' => 'Generate Claude Code and Cursor 2 prompts from task details.',
            'status' => 'Ready',
            'agent' => 'human',
            'priority' => 'medium',
            'tags' => ['frontend', 'ai'],
            'context' => 'The core value proposition of ProjectHub is converting structured task data into actionable AI prompts for coding assistants.',
            'instructions' => "On the task detail page, add a prompt generator section with two tabs: Claude Code and Cursor 2. Each combines context + instructions + acceptance_criteria into a formatted prompt with copy-to-clipboard functionality.",
            'acceptance_criteria' => "Claude Code prompt includes background, goal, acceptance criteria, constraints, deliverables\nCursor 2 prompt includes TODO checklist, file hints, testing instructions\nCopy button works",
        ]);

        $task4 = Task::create([
            'epic_id' => $epic->id,
            'title' => 'Kanban board view',
            'description' => 'Implement a simple kanban board for tasks grouped by status.',
            'status' => 'Backlog',
            'agent' => 'cursor2',
            'priority' => 'medium',
            'tags' => ['frontend'],
            'context' => 'Each epic needs a visual kanban board showing tasks in 5 status columns.',
            'instructions' => 'Create a kanban view at /epics/{epic}/kanban. Display 5 columns: Backlog, Ready, InProgress, Review, Done. Each card shows title, priority, agent. Status change via arrow buttons (no drag-drop required).',
            'acceptance_criteria' => "5 columns displayed\nTasks grouped by status\nStatus change buttons work\nLinks to task detail",
        ]);

        $task5 = Task::create([
            'epic_id' => $epic->id,
            'title' => 'Project file management',
            'description' => 'Upload, download, and delete project files.',
            'status' => 'Review',
            'agent' => 'claude_code',
            'priority' => 'low',
            'tags' => ['backend', 'storage'],
            'context' => 'Projects need file storage for documents, images, and archives. Files are stored privately and only accessible to assigned users.',
            'instructions' => "Create ProjectFile model and migration. Add file upload/download/delete functionality. Store files in private disk. Limit to 20MB and specific file types.",
            'acceptance_criteria' => "File upload works with validation\nDownload through controller (no public URL)\nAdmin can delete all files\nUsers can only delete own uploads\nFile list shows metadata",
        ]);

        // 6. Artifacts for task 1
        TaskArtifact::create([
            'task_id' => $task1->id,
            'type' => 'commit',
            'value' => 'abc1234',
            'note' => 'Initial auth implementation',
        ]);

        TaskArtifact::create([
            'task_id' => $task1->id,
            'type' => 'file_path',
            'value' => 'app/Http/Controllers/Auth/AuthenticatedSessionController.php',
        ]);

        // 7. Reviews for task 1
        TaskReview::create([
            'task_id' => $task1->id,
            'result' => 'changes_requested',
            'note' => 'Registration route still accessible. Please remove or redirect.',
        ]);

        TaskReview::create([
            'task_id' => $task1->id,
            'result' => 'pass',
            'note' => 'Auth system working correctly. Registration disabled. Password reset flow works.',
        ]);

        // Review for task 5 (in Review status)
        TaskReview::create([
            'task_id' => $task5->id,
            'result' => 'changes_requested',
            'note' => 'File size validation not working for files over 20MB. Please fix.',
        ]);
    }
}
