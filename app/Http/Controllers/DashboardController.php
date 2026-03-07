<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $projects = Project::withCount(['epics', 'users'])->get();
        } else {
            $projects = Project::accessibleTo($user)->withCount('epics')->get();
        }

        return view('dashboard', compact('projects'));
    }
}
