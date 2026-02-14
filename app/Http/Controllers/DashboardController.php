<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $projects = \App\Models\Project::withCount(['epics', 'users'])->get();
        } else {
            $projects = $user->projects()->withCount('epics')->get();
        }

        return view('dashboard', compact('projects'));
    }
}
