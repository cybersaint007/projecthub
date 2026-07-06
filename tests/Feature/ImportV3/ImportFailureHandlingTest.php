<?php

namespace Tests\Feature\ImportV3;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A malformed field that survives schema validation but violates a DB
 * constraint must surface as a form error — never an HTTP 500.
 *
 * Regression: tasks carried `agent` as an object ({"type":"claude_code",...})
 * where the column expects a scalar string, raising "Array to string
 * conversion" from deep inside ProjectImporter. The controller had no guard,
 * so Laravel returned a 500 and the user got no idea which record was at fault.
 */
class ImportFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * A payload that passes SchemaValidator but breaks at the DB layer.
     *
     * `context` is a scalar text column assigned straight from the payload, so
     * an array value raises "Array to string conversion" mid-transaction —
     * the same class of failure as the original object-`agent` bug.
     */
    private function payloadWithBrokenField(): array
    {
        return [
            'schema_version' => '3.0',
            'project' => ['name' => 'Broken Import Project', 'code' => 'BROKEN'],
            'epics' => [[
                'title' => 'Epic',
                'tasks' => [[
                    'title' => 'Task with array in a scalar column',
                    'context' => ['not', 'a', 'string'],
                ]],
            ]],
        ];
    }

    public function test_global_import_returns_form_error_not_500_on_db_failure(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('backlog.import-v3.global'))
            ->post(route('backlog.import-v3.global.apply'), [
                'json_payload' => json_encode($this->payloadWithBrokenField()),
            ]);

        $response->assertRedirect(route('backlog.import-v3.global'));
        $response->assertSessionHasErrors('import');

        // Transaction rolled back — nothing persisted.
        $this->assertDatabaseMissing('projects', ['code' => 'BROKEN']);
    }

    public function test_project_scoped_import_returns_form_error_not_500(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['owner_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->from(route('backlog.import-v3', $project))
            ->post(route('backlog.import-v3.apply', $project), [
                'json_payload' => json_encode($this->payloadWithBrokenField()),
            ]);

        $response->assertRedirect(route('backlog.import-v3', $project));
        $response->assertSessionHasErrors('import');
    }

    public function test_valid_import_still_succeeds(): void
    {
        $payload = [
            'schema_version' => '3.0',
            'project' => ['name' => 'Good Project', 'code' => 'GOOD'],
            'epics' => [[
                'title' => 'Epic',
                'tasks' => [[
                    'title' => 'Task with scalar agent',
                    'agent' => 'claude_code',
                ]],
            ]],
        ];

        $response = $this->actingAs($this->admin())
            ->post(route('backlog.import-v3.global.apply'), [
                'json_payload' => json_encode($payload),
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['code' => 'GOOD']);
    }
}
