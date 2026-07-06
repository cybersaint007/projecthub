<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ImportV3\ImportResult;
use App\Services\ImportV3\ProjectImporter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Shared guard for V3 backlog imports.
 *
 * The import runs a batch of upserts inside a single DB transaction. A single
 * malformed field (e.g. a `agent`/`assignee` object where a scalar is expected,
 * or a value that violates a column constraint) raises a QueryException from
 * deep inside ProjectImporter. Without this guard that exception is uncaught
 * and the user gets an opaque HTTP 500 with no idea which record was at fault.
 *
 * runImport() converts any failure into a concise, localized message the caller
 * can hand back to the form via ->withErrors(), while the full stack trace still
 * goes to the log via report() for debugging.
 */
trait RunsBacklogImport
{
    /**
     * Run the importer, catching any failure.
     *
     * @return array{0: ?ImportResult, 1: ?string} [result, errorMessage]
     *                                             On success: [ImportResult, null]. On failure: [null, message].
     */
    protected function runImport(ProjectImporter $importer, array $data): array
    {
        try {
            return [$importer->import($data), null];
        } catch (\Throwable $e) {
            // Full detail (SQL, bindings, stack) goes to the log; the whole
            // transaction has already rolled back, so nothing was persisted.
            report($e);

            return [null, __('ui.error_import_failed', ['error' => $this->importFailureReason($e)])];
        }
    }

    /**
     * Distil a throwable into a short, user-facing reason.
     *
     * A QueryException's message embeds the full SQL and connection details;
     * we keep only the leading cause (e.g. "Array to string conversion") so the
     * form shows something actionable without leaking the schema.
     */
    private function importFailureReason(\Throwable $e): string
    {
        $reason = $e instanceof QueryException
            ? Str::of($e->getMessage())->before(' (Connection:')->trim()->toString()
            : trim($e->getMessage());

        return $reason !== '' ? $reason : class_basename($e);
    }
}
