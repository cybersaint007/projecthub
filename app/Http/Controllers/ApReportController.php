<?php

namespace App\Http\Controllers;

use App\Services\AgedApReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ApReportController extends Controller
{
    public function agedAp(Request $request, AgedApReportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))->startOfDay()
            : Carbon::today();

        $report = $service->generate($asOf);

        return view('ap.aged', compact('report'));
    }
}
