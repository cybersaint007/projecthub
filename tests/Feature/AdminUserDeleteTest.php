<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_soft_delete_a_user(): void
    {
        $target = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_another_admin_when_more_than_one_exists(): void
    {
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $otherAdmin))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $otherAdmin->id]);
    }

    public function test_last_remaining_admin_is_protected(): void
    {
        // $this->admin is the only admin. Attempting to delete the sole admin is
        // blocked (the self-delete guard fires first; the last-admin guard backs it up).
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
        $this->assertSame(1, User::where('is_admin', true)->count());
    }

    public function test_admin_can_restore_a_soft_deleted_user(): void
    {
        $target = User::factory()->create(['is_admin' => false]);
        $target->delete();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.restore', $target->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_restored_user_can_log_in_again(): void
    {
        $target = User::factory()->create([
            'is_admin' => false,
            'password' => bcrypt('secret-password'),
        ]);
        $target->delete();
        $target->restore();

        $response = $this->post(route('login'), [
            'email' => $target->email,
            'password' => 'secret-password',
        ]);

        $this->assertAuthenticatedAs($target->fresh());
        $response->assertRedirect();
    }

    public function test_non_admin_cannot_restore_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);
        $target->delete();

        $this->actingAs($user)
            ->post(route('admin.users.restore', $target->id))
            ->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_index_lists_deleted_users_for_admin(): void
    {
        $active = User::factory()->create(['is_admin' => false, 'name' => 'Active Person']);
        $deleted = User::factory()->create(['is_admin' => false, 'name' => 'Deleted Person']);
        $deleted->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Active Person')
            ->assertSee('Deleted Person');
    }

    public function test_non_admin_cannot_delete_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_soft_deleted_user_cannot_log_in(): void
    {
        $target = User::factory()->create([
            'is_admin' => false,
            'password' => bcrypt('secret-password'),
        ]);
        $target->delete();

        $response = $this->post(route('login'), [
            'email' => $target->email,
            'password' => 'secret-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
