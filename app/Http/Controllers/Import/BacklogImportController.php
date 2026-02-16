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
        $request->validate([
            'file' => ['required', 'file', 'mimes:json,application/json', 'max:2048'],
            'dry_run' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Please select a JSON file to upload.',
            'file.mimes' => 'The file must be a JSON file.',
            'file.max' => 'The file size must not exceed 2MB.',
        ]);

        try {
            $file = $request->file('file');
            $dryRun = $request->boolean('dry_run');

            // Read file content
            $jsonContent = file_get_contents($file->getRealPath());

            // Import using service
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
}
