<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'is_admin' => 'boolean',
        ]);

        $plainPassword = $this->generateStrongPassword();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $plainPassword,
            'is_admin' => $data['is_admin'] ?? false,
            'force_password_reset' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('new_user_password', $plainPassword)
            ->with('new_user_name', $user->name)
            ->with('new_user_email', $user->email);
    }

    public function show(Request $request, User $user)
    {
        $projects = Project::all();
        $assignedProjectIds = $user->projects()->pluck('projects.id')->toArray();

        return view('admin.users.show', compact('user', 'projects', 'assignedProjectIds'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'is_admin' => 'boolean',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => $data['is_admin'] ?? false,
        ]);

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated.');
    }

    public function resetPassword(User $user)
    {
        $plainPassword = $this->generateStrongPassword();

        $user->update([
            'password' => $plainPassword,
            'force_password_reset' => true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('new_user_password', $plainPassword)
            ->with('new_user_name', $user->name)
            ->with('new_user_email', $user->email);
    }

    public function syncProjects(Request $request, User $user)
    {
        $data = $request->validate([
            'projects' => 'nullable|array',
            'projects.*' => 'exists:projects,id',
        ]);

        $user->projects()->sync($data['projects'] ?? []);

        return redirect()->route('admin.users.show', $user)->with('status', 'Project assignments updated.');
    }

    private function generateStrongPassword(int $length = 18): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
