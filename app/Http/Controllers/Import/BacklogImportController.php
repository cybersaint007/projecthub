<?php

namespace App\Http\Controllers\Import;

use App\Exceptions\BacklogImportException;
use App\Http\Controllers\Controller;
use App\Services\BacklogImportService;
use Illuminate\Http\Request;

class BacklogImportController extends Controller
{
    /**
     * Show the import form
     */
    public function show()
    {
        return view('imports.backlog');
    }

    /**
     * Handle the import request
     */
    public function store(Request $request, BacklogImportService $service)
    {
        $jsonPayload = trim($request->input('json_payload', ''));
        $hasPayload = $jsonPayload !== '';
        $hasFile = $request->hasFile('file');

        if (! $hasPayload && ! $hasFile) {
            return back()
                ->withErrors(['import' => 'Please provide JSON via the textarea or upload a JSON file.'])
                ->withInput();
        }

        // Validate file only when no textarea payload
        if (! $hasPayload && $hasFile) {
            $request->validate([
                'file' => ['file', 'mimes:json,application/json', 'max:2048'],
            ], [
                'file.mimes' => 'The file must be a JSON file.',
                'file.max' => 'The file size must not exceed 2MB.',
            ]);
        }

        try {
            if ($hasPayload) {
                $jsonContent = $jsonPayload;
            } else {
                $jsonContent = file_get_contents($request->file('file')->getRealPath());
            }

            $dryRun = $request->boolean('dry_run');
            $result = $service->importFromJsonString($jsonContent, $dryRun);

            if ($dryRun) {
                return back()->with([
                    'status' => 'Dry-run completed successfully.',
                    'result' => $result,
                    'isDryRun' => true,
                ]);
            }

            return back()->with([
                'status' => 'Import completed successfully.',
                'result' => $result,
                'isDryRun' => false,
            ]);
        } catch (BacklogImportException $e) {
            return back()->withErrors(['import' => $e->getMessage()])
                ->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['import' => 'An unexpected error occurred: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Download the example JSON file
     */
    public function downloadExample()
    {
        $path = resource_path('examples/projecthub-import-example.json');

        return response()->download($path, 'projecthub-import-example.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
