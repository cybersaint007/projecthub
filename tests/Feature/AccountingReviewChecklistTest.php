<?php

namespace Tests\Feature;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReview;
use App\Models\User;
use Database\Seeders\AccountingReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the reviewer checklist workflow covering the accounting engine domains:
 * General Ledger, Posting Engine, Accounts Receivable, Accounts Payable.
 *
 * Pass reviews require the four truth-audit checkboxes (route_exists, ui_exists,
 * service_exists, test_exists) all set to 1. This mirrors the reality that a
 * reviewer must verify every implementation artifact before signing off.
 */
class AccountingReviewChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $reviewer;
    private Project $project;
    private Epic $epicGL;
    private Task $taskInReview; // task in Review status

    /** Truth-audit fields required for a pass review. */
    private array $truthAudit = [
        'route_exists'   => '1',
        'ui_exists'      => '1',
        'service_exists' => '1',
        'test_exists'    => '1',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin'             => true,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);

        $this->reviewer = User::factory()->create([
            'is_admin'             => false,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);

        $this->project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $this->project->users()->attach([$this->reviewer->id => ['role' => 'viewer']]);

        $this->epicGL = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title'      => 'General Ledger',
        ]);

        $this->taskInReview = Task::factory()->create([
            'epic_id'  => $this->epicGL->id,
            'title'    => 'Journal entry double-entry enforcement',
            'status'   => 'Review',
            'priority' => Task::PRIORITY_HIGH,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Pass review — truth audit required
    // ──────────────────────────────────────────────────────────────────────

    public function test_admin_can_submit_pass_review_with_all_audit_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), array_merge([
                'result' => 'pass',
                'note'   => 'All double-entry constraints verified.',
            ], $this->truthAudit))
            ->assertRedirect();

        $this->assertDatabaseHas('task_reviews', [
            'task_id'        => $this->taskInReview->id,
            'result'         => 'pass',
            'route_exists'   => true,
            'ui_exists'      => true,
            'service_exists' => true,
            'test_exists'    => true,
        ]);
    }

    public function test_pass_review_transitions_task_to_done(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), array_merge([
                'result' => 'pass',
                'note'   => 'Balanced JE accepted. Unbalanced JE rejected. Closed-period block working.',
            ], $this->truthAudit));

        $this->assertEquals('Done', $this->taskInReview->fresh()->status);
    }

    public function test_pass_without_truth_audit_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), [
                'result' => 'pass',
                'note'   => 'Nothing verified yet.',
                // truth-audit fields omitted intentionally
            ])
            ->assertSessionHasErrors('truth_audit');

        $this->assertEquals('Review', $this->taskInReview->fresh()->status);
        $this->assertDatabaseMissing('task_reviews', ['task_id' => $this->taskInReview->id]);
    }

    public function test_pass_with_partial_audit_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), [
                'result'       => 'pass',
                'note'         => 'Missing test coverage.',
                'route_exists' => '1',
                'ui_exists'    => '1',
                // service_exists and test_exists omitted
            ])
            ->assertSessionHasErrors('truth_audit');

        $this->assertEquals('Review', $this->taskInReview->fresh()->status);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Changes requested — no truth audit needed
    // ──────────────────────────────────────────────────────────────────────

    public function test_changes_requested_review_reverts_task_to_in_progress(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), [
                'result' => 'changes_requested',
                'note'   => 'GL entry still created for draft invoices — must only stage on finalise.',
            ])
            ->assertRedirect();

        $this->assertEquals('InProgress', $this->taskInReview->fresh()->status);
        $this->assertDatabaseHas('task_reviews', [
            'task_id' => $this->taskInReview->id,
            'result'  => 'changes_requested',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Access control
    // ──────────────────────────────────────────────────────────────────────

    public function test_assigned_project_member_can_submit_pass_review(): void
    {
        $this->actingAs($this->reviewer)
            ->post(route('reviews.store', $this->taskInReview), array_merge([
                'result' => 'pass',
                'note'   => 'Reviewer verified GL integrity checks pass.',
            ], $this->truthAudit))
            ->assertRedirect();

        $this->assertDatabaseHas('task_reviews', [
            'task_id' => $this->taskInReview->id,
            'result'  => 'pass',
        ]);
    }

    public function test_unassigned_user_cannot_submit_review(): void
    {
        $outsider = User::factory()->create(['is_admin' => false]);

        $this->actingAs($outsider)
            ->post(route('reviews.store', $this->taskInReview), [
                'result' => 'changes_requested',
                'note'   => 'Should not reach here.',
            ])
            ->assertStatus(403);
    }

    public function test_review_requires_result_and_note(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), [])
            ->assertSessionHasErrors(['result', 'note']);
    }

    public function test_review_rejects_invalid_result_value(): void
    {
        $this->actingAs($this->admin)
            ->post(route('reviews.store', $this->taskInReview), [
                'result' => 'approved', // not a valid TaskReview result
                'note'   => 'Testing invalid enum.',
            ])
            ->assertSessionHasErrors(['result']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Review history (multiple rounds)
    // ──────────────────────────────────────────────────────────────────────

    public function test_task_accumulates_multiple_reviews(): void
    {
        $this->actingAs($this->admin);

        // Round 1: changes requested
        $this->post(route('reviews.store', $this->taskInReview), [
            'result' => 'changes_requested',
            'note'   => 'COA seed missing Retained Earnings account.',
        ]);

        // Manually return to Review for second pass
        $this->taskInReview->update(['status' => 'Review']);

        // Round 2: pass with full audit
        $this->post(route('reviews.store', $this->taskInReview), array_merge([
            'result' => 'pass',
            'note'   => 'All 6 seed accounts present. Uniqueness constraint verified.',
        ], $this->truthAudit));

        $this->assertEquals(2, $this->taskInReview->reviews()->count());
        $this->assertEquals('Done', $this->taskInReview->fresh()->status);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Seeder structural assertions
    // ──────────────────────────────────────────────────────────────────────

    public function test_accounting_review_seeder_creates_expected_structure(): void
    {
        $this->seed(AccountingReviewSeeder::class);

        $project = Project::where('code', 'ACC-ENGINE')->first();
        $this->assertNotNull($project, 'ACC-ENGINE project should be created by seeder');

        $epicTitles = $project->epics()->pluck('title')->toArray();
        $this->assertContains('General Ledger', $epicTitles);
        $this->assertContains('Posting Engine', $epicTitles);
        $this->assertContains('Accounts Receivable', $epicTitles);
        $this->assertContains('Accounts Payable', $epicTitles);
    }

    public function test_seeder_creates_four_tasks_per_epic(): void
    {
        $this->seed(AccountingReviewSeeder::class);

        $project = Project::where('code', 'ACC-ENGINE')->first();

        foreach ($project->epics as $epic) {
            $count = $epic->tasks()->count();
            $this->assertEquals(4, $count, "Epic '{$epic->title}' should have 4 tasks, got {$count}");
        }
    }

    public function test_seeder_creates_review_records_with_correct_results(): void
    {
        $this->seed(AccountingReviewSeeder::class);

        // GL epic: CoA task should have 2 reviews (changes_requested then pass)
        $project = Project::where('code', 'ACC-ENGINE')->first();
        $glEpic  = $project->epics()->where('title', 'General Ledger')->first();
        $coaTask = $glEpic->tasks()->where('title', 'Chart of accounts structure')->first();

        $reviews = $coaTask->reviews()->orderBy('id')->get();
        $this->assertCount(2, $reviews);
        $this->assertEquals('changes_requested', $reviews->first()->result);
        $this->assertEquals('pass', $reviews->last()->result);
    }

    public function test_seeder_sets_expected_task_statuses(): void
    {
        $this->seed(AccountingReviewSeeder::class);

        $project = Project::where('code', 'ACC-ENGINE')->first();
        $glEpic  = $project->epics()->where('title', 'General Ledger')->first();

        $statusMap = $glEpic->tasks()->pluck('status', 'title')->toArray();

        $this->assertEquals('Done',    $statusMap['Chart of accounts structure']);
        $this->assertEquals('Review',  $statusMap['Journal entry double-entry enforcement']);
        $this->assertEquals('Ready',   $statusMap['Trial balance generation']);
        $this->assertEquals('Backlog', $statusMap['Period-end close']);
    }

    public function test_seeder_assigns_reviewer_to_project(): void
    {
        $this->seed(AccountingReviewSeeder::class);

        $project  = Project::where('code', 'ACC-ENGINE')->first();
        $reviewer = User::where('email', 'reviewer@projecthub.local')->first();

        $this->assertNotNull($reviewer, 'Accounting reviewer user should exist after seeding');
        $this->assertTrue(
            $project->accessUsers()->where('users.id', $reviewer->id)->exists(),
            'Reviewer should be attached to ACC-ENGINE project'
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Checklist coverage — all tasks have acceptance criteria
    // ──────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('epicTitleProvider')]
    public function test_all_tasks_in_epic_have_acceptance_criteria(string $epicTitle): void
    {
        $this->seed(AccountingReviewSeeder::class);

        $project = Project::where('code', 'ACC-ENGINE')->first();
        $epic    = $project->epics()->where('title', $epicTitle)->first();

        foreach ($epic->tasks as $task) {
            $this->assertNotEmpty(
                $task->acceptance_criteria,
                "Task '{$task->title}' in epic '{$epicTitle}' must have acceptance criteria"
            );
        }
    }

    public static function epicTitleProvider(): array
    {
        return [
            'General Ledger'       => ['General Ledger'],
            'Posting Engine'       => ['Posting Engine'],
            'Accounts Receivable'  => ['Accounts Receivable'],
            'Accounts Payable'     => ['Accounts Payable'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Guard rails: seeder is idempotent for admin user
    // ──────────────────────────────────────────────────────────────────────

    public function test_seeder_reuses_existing_admin_user(): void
    {
        User::factory()->create([
            'email'    => 'admin@projecthub.local',
            'is_admin' => true,
        ]);

        $this->seed(AccountingReviewSeeder::class);

        $adminCount = User::where('email', 'admin@projecthub.local')->count();
        $this->assertEquals(1, $adminCount, 'Should not create a duplicate admin user');
    }
}
