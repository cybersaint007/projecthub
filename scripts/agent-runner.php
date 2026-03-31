#!/usr/bin/env php
<?php

/**
 * ProjectHub Agent Runner
 *
 * Polls the ProjectHub Agent API for tasks, executes them via the Claude Code CLI,
 * and streams output back as task logs.
 *
 * Usage:
 *   php scripts/agent-runner.php [--dry-run] [--once]
 *
 * Configuration via environment variables or a local agent-runner.env file.
 * See scripts/agent-runner.example.env for all options.
 */

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

define('RUNNER_VERSION', '1.0.0');
define('RUNNER_START', microtime(true));

// Load .env file from cwd or script directory (agent-runner.env takes priority over .env)
foreach ([getcwd() . '/agent-runner.env', __DIR__ . '/agent-runner.env', getcwd() . '/.env'] as $envFile) {
    if (file_exists($envFile)) {
        loadEnvFile($envFile);
        break;
    }
}

// ─── CLI Argument Parsing ─────────────────────────────────────────────────────

$dryRun = in_array('--dry-run', $argv, true) || getEnvBool('AGENT_DRY_RUN');
$runOnce = in_array('--once', $argv, true);

// ─── Configuration ────────────────────────────────────────────────────────────

$config = [
    'api_base'       => rtrim(requireEnv('AGENT_API_BASE'), '/'),
    'token'          => requireEnv('AGENT_TOKEN'),
    'project_id'     => requireEnv('AGENT_PROJECT_ID'),
    'agent_type'     => getenv('AGENT_TYPE')      ?: 'claude_code',
    'worker_id'      => getenv('AGENT_WORKER_ID') ?: gethostname(),
    'repo_path'      => getenv('AGENT_REPO_PATH') ?: getcwd(),
    'poll_interval'  => (int)(getenv('AGENT_POLL_INTERVAL') ?: 30),
    'dry_run'        => $dryRun,
    // Extra flags appended to the claude invocation (space-separated)
    'claude_flags'   => getenv('AGENT_CLAUDE_FLAGS') ?: '--dangerously-skip-permissions',
];

// Validate repo path
if (!is_dir($config['repo_path'])) {
    fatal("AGENT_REPO_PATH does not exist: {$config['repo_path']}");
}

// ─── Locate the claude binary ─────────────────────────────────────────────────

$claudeBin = trim((string)shell_exec('command -v claude 2>/dev/null'));
if ($claudeBin === '') {
    fatal(
        "claude CLI not found in PATH.\n" .
        "Install Claude Code: https://docs.anthropic.com/en/docs/claude-code/getting-started"
    );
}

// ─── Signal Handling ──────────────────────────────────────────────────────────

$interrupted = false;
$inTask      = false;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    $signalHandler = static function (int $sig) use (&$interrupted, &$inTask): void {
        $name = $sig === SIGTERM ? 'SIGTERM' : 'SIGINT';
        if ($inTask) {
            log_msg("Received {$name} — will exit after current task finishes.");
        } else {
            log_msg("Received {$name} — shutting down.");
        }
        $interrupted = true;
    };
    pcntl_signal(SIGINT,  $signalHandler);
    pcntl_signal(SIGTERM, $signalHandler);
} else {
    log_msg("Warning: pcntl extension not available — graceful shutdown on SIGINT/SIGTERM disabled.");
}

// ─── Startup Banner ───────────────────────────────────────────────────────────

log_msg("ProjectHub Agent Runner v" . RUNNER_VERSION);
log_msg("Worker:    {$config['worker_id']}");
log_msg("Project:   {$config['project_id']}");
log_msg("Agent:     {$config['agent_type']}");
log_msg("Repo:      {$config['repo_path']}");
log_msg("API:       {$config['api_base']}");
log_msg("Dry-run:   " . ($config['dry_run'] ? 'YES' : 'no'));
log_msg("Once:      " . ($runOnce ? 'YES' : 'no'));
log_msg("Claude:    {$claudeBin}");
log_msg(str_repeat('-', 60));

// ─── Main Loop ────────────────────────────────────────────────────────────────

while (true) {
    if ($interrupted) {
        log_msg("Shutdown requested. Exiting.");
        exit(0);
    }

    // ── Step 1: Poll for the next task ────────────────────────────────────────
    $nextUrl = sprintf(
        '%s/api/agent/projects/%s/tasks/next?worker_id=%s&agent_type=%s',
        $config['api_base'],
        urlencode($config['project_id']),
        urlencode($config['worker_id']),
        urlencode($config['agent_type'])
    );

    $nextResp = apiRequest('GET', $nextUrl, $config);

    if ($nextResp['error']) {
        log_msg("Network error polling for task: {$nextResp['error']}");
        sleep($config['poll_interval']);
        continue;
    }

    if ($nextResp['code'] !== 200) {
        log_msg("Unexpected response from /tasks/next (HTTP {$nextResp['code']}): {$nextResp['body']}");
        sleep($config['poll_interval']);
        continue;
    }

    $task = $nextResp['data']['task'] ?? null;

    if ($task === null) {
        log_msg("No tasks available. Sleeping {$config['poll_interval']}s…");
        sleep($config['poll_interval']);
        continue;
    }

    log_msg("Found task #{$task['id']}: {$task['title']} (status: {$task['status']})");

    // ── Step 2: Claim the task ────────────────────────────────────────────────
    $claimUrl  = "{$config['api_base']}/api/agent/tasks/{$task['id']}/claim";
    $claimResp = apiRequest('POST', $claimUrl, $config, ['worker_id' => $config['worker_id']]);

    if ($claimResp['error']) {
        log_msg("Network error claiming task #{$task['id']}: {$claimResp['error']}");
        sleep(5);
        continue;
    }

    if ($claimResp['code'] === 409) {
        log_msg("Task #{$task['id']} already claimed by another worker. Backing off 5s…");
        sleep(5);
        continue;
    }

    if ($claimResp['code'] !== 200) {
        log_msg("Failed to claim task #{$task['id']} (HTTP {$claimResp['code']}): {$claimResp['body']}");
        sleep(5);
        continue;
    }

    $leaseToken = $claimResp['data']['lease_token'] ?? null;
    $leasedUntil = $claimResp['data']['leased_until'] ?? null;

    if (!$leaseToken) {
        log_msg("Claim response missing lease_token for task #{$task['id']}. Skipping.");
        sleep(5);
        continue;
    }

    log_msg("Claimed task #{$task['id']} (lease until {$leasedUntil})");

    // ── Step 3: Fetch the bundle ──────────────────────────────────────────────
    $bundleUrl  = "{$config['api_base']}/api/agent/tasks/{$task['id']}/bundle?lease_token=" . urlencode($leaseToken);
    $bundleResp = apiRequest('GET', $bundleUrl, $config);

    if ($bundleResp['error'] || $bundleResp['code'] !== 200) {
        $err = $bundleResp['error'] ?: "HTTP {$bundleResp['code']}: {$bundleResp['body']}";
        log_msg("Failed to fetch bundle for task #{$task['id']}: {$err}");
        // Release back to Ready so it can be retried
        patchStatus($config, $task['id'], $leaseToken, 'Ready');
        sleep(5);
        continue;
    }

    $bundle       = $bundleResp['data'];
    $promptContent = $bundle['prompt']['content'] ?? '';
    $taskTitle     = $bundle['task']['title']   ?? $task['title'];
    $taskId        = $task['id'];

    if (empty($promptContent)) {
        log_msg("Bundle prompt is empty for task #{$taskId}. Releasing.");
        patchStatus($config, $taskId, $leaseToken, 'Ready');
        sleep(5);
        continue;
    }

    // ── Dry-run: print bundle and skip execution ───────────────────────────────
    if ($config['dry_run']) {
        log_msg("=== DRY-RUN: Bundle for task #{$taskId}: {$taskTitle} ===");
        log_msg("--- Prompt ---");
        echo $promptContent . "\n";
        log_msg("--- End Prompt ---");
        log_msg("Dry-run complete. Task NOT executed, NOT moved to InProgress.");

        if ($runOnce) {
            log_msg("--once flag set. Exiting.");
            exit(0);
        }
        sleep(2);
        continue;
    }

    // ── Step 4: Mark InProgress ───────────────────────────────────────────────
    $inTask = true;
    $statusResp = patchStatus($config, $taskId, $leaseToken, 'InProgress');
    if (!$statusResp) {
        log_msg("Failed to set task #{$taskId} to InProgress. Releasing.");
        $inTask = false;
        sleep(5);
        continue;
    }

    // ── Step 5: Log start ─────────────────────────────────────────────────────
    postLog($config, $taskId, $leaseToken, 'info', "Agent runner starting task: {$taskTitle}");

    // ── Step 6: Write prompt to temp file ─────────────────────────────────────
    $promptFile = sys_get_temp_dir() . "/ph_task_{$taskId}.md";
    if (file_put_contents($promptFile, $promptContent) === false) {
        log_msg("Failed to write prompt file: {$promptFile}");
        postLog($config, $taskId, $leaseToken, 'error', "Agent runner failed to write prompt file.");
        patchStatus($config, $taskId, $leaseToken, 'Ready');
        $inTask = false;
        sleep(5);
        continue;
    }

    // ── Step 7: Execute Claude CLI ────────────────────────────────────────────
    log_msg("Executing claude on task #{$taskId}…");

    $exitCode = executeClaudeTask(
        claudeBin:   $claudeBin,
        repoPath:    $config['repo_path'],
        promptFile:  $promptFile,
        claudeFlags: $config['claude_flags'],
        taskId:      $taskId,
        leaseToken:  $leaseToken,
        config:      $config
    );

    // ── Step 8: Update status based on exit code ──────────────────────────────
    if ($exitCode === 0) {
        postLog($config, $taskId, $leaseToken, 'info', "Execution completed successfully (exit 0).");
        patchStatus($config, $taskId, $leaseToken, 'Review');
        log_msg("Task #{$taskId} moved to Review.");
    } else {
        postLog($config, $taskId, $leaseToken, 'error', "Execution failed with exit code {$exitCode}.");
        patchStatus($config, $taskId, $leaseToken, 'Ready');
        log_msg("Task #{$taskId} returned to Ready (exit code {$exitCode}).");
    }

    // ── Step 9: Clean up ──────────────────────────────────────────────────────
    @unlink($promptFile);
    $inTask = false;

    if ($runOnce || $interrupted) {
        log_msg($interrupted ? "Shutdown requested. Exiting." : "--once flag set. Exiting.");
        exit($exitCode === 0 ? 0 : 1);
    }

    // ── Step 10: Brief pause before next poll ─────────────────────────────────
    sleep(2);
}

// ─── Functions ────────────────────────────────────────────────────────────────

/**
 * Execute Claude CLI on the given prompt file, streaming output as task logs.
 * Returns the process exit code.
 */
function executeClaudeTask(
    string $claudeBin,
    string $repoPath,
    string $promptFile,
    string $claudeFlags,
    int    $taskId,
    string $leaseToken,
    array  $config
): int {
    // Build the command — claude reads prompt from stdin (temp file)
    $flagParts = array_filter(array_map('trim', explode(' ', $claudeFlags)));
    $flagParts = array_merge(['--print'], array_diff($flagParts, ['--print']));
    $cmd = escapeshellarg($claudeBin) . ' ' . implode(' ', array_map('escapeshellarg', $flagParts));

    $descriptors = [
        0 => ['file', $promptFile, 'r'],  // stdin: our prompt file (no shell quoting issues)
        1 => ['pipe', 'w'],               // stdout
        2 => ['pipe', 'w'],               // stderr
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $repoPath);

    if (!is_resource($proc)) {
        log_msg("Failed to start claude process.");
        postLog($config, $taskId, $leaseToken, 'error', "Failed to start claude process.");
        return 1;
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdoutBuf   = '';
    $stderrBuf   = '';
    $stdoutDone  = false;
    $stderrDone  = false;
    $capturedExit = null;

    while (!$stdoutDone || !$stderrDone) {
        $readStreams = [];
        if (!$stdoutDone && isset($pipes[1]) && is_resource($pipes[1])) {
            $readStreams[] = $pipes[1];
        }
        if (!$stderrDone && isset($pipes[2]) && is_resource($pipes[2])) {
            $readStreams[] = $pipes[2];
        }

        if (empty($readStreams)) {
            break;
        }

        $write = $except = null;
        $n = stream_select($readStreams, $write, $except, 1, 0);

        if ($n === false) {
            break;
        }

        foreach ($readStreams as $stream) {
            $isStdout = $stream === $pipes[1];
            $chunk = fread($stream, 8192);

            if ($chunk !== false && $chunk !== '') {
                if ($isStdout) {
                    // Accumulate stdout — posted as one entry at EOF so the
                    // work log is not fragmented into per-line entries.
                    $stdoutBuf .= $chunk;
                } else {
                    // Stream stderr line-by-line so errors appear immediately.
                    $stderrBuf .= $chunk;
                    $stderrBuf = flushLinesToLog($config, $taskId, $leaseToken, 'error', $stderrBuf);
                }
            }

            if (feof($stream)) {
                if ($isStdout) {
                    if ($stdoutBuf !== '') {
                        // Post the entire Claude response as a single log entry.
                        postLog($config, $taskId, $leaseToken, 'info', stripAnsi(trim($stdoutBuf)));
                        $stdoutBuf = '';
                    }
                    fclose($pipes[1]);
                    $stdoutDone = true;
                } else {
                    if ($stderrBuf !== '') {
                        postLog($config, $taskId, $leaseToken, 'error', stripAnsi($stderrBuf));
                        $stderrBuf = '';
                    }
                    fclose($pipes[2]);
                    $stderrDone = true;
                }
            }
        }

        // Capture exit code on first detection (proc_get_status exitcode is only
        // accurate on the first call after process exits)
        if ($capturedExit === null) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $capturedExit = $status['exitcode'];
            }
        }
    }

    $procClose = proc_close($proc);
    return $capturedExit ?? $procClose;
}

/**
 * Flush complete newline-delimited lines from a buffer to the task log API.
 * Returns the remaining incomplete line (no trailing newline yet).
 */
function flushLinesToLog(array $config, int $taskId, string $leaseToken, string $level, string $buf): string
{
    while (($nl = strpos($buf, "\n")) !== false) {
        $line = substr($buf, 0, $nl);
        $buf  = substr($buf, $nl + 1);
        $line = stripAnsi($line);
        if ($line !== '') {
            postLog($config, $taskId, $leaseToken, $level, $line);
        }
    }
    return $buf;
}

/**
 * POST a log entry to the Agent API.
 */
function postLog(array $config, int $taskId, string $leaseToken, string $level, string $message): void
{
    $url  = "{$config['api_base']}/api/agent/tasks/{$taskId}/logs";
    $resp = apiRequest('POST', $url, $config, [
        'lease_token' => $leaseToken,
        'level'       => $level,
        'message'     => mb_substr($message, 0, 65000), // API max 65535
    ]);

    if ($resp['error'] || $resp['code'] !== 201) {
        // Don't crash — just emit a local warning
        log_msg("Warning: failed to post log (HTTP {$resp['code']}): {$resp['error']}");
    }
}

/**
 * PATCH task status via the Agent API. Returns true on success.
 */
function patchStatus(array $config, int $taskId, string $leaseToken, string $status): bool
{
    $url  = "{$config['api_base']}/api/agent/tasks/{$taskId}/status";
    $resp = apiRequest('PATCH', $url, $config, [
        'lease_token' => $leaseToken,
        'status'      => $status,
    ]);

    if ($resp['error'] || $resp['code'] !== 200) {
        log_msg("Warning: failed to PATCH status={$status} for task #{$taskId} (HTTP {$resp['code']}): {$resp['error']}");
        return false;
    }
    return true;
}

/**
 * Generic HTTP request using curl. No Composer dependencies.
 *
 * @return array{code: int, body: string, data: mixed, error: string}
 */
function apiRequest(string $method, string $url, array $config, ?array $data = null): array
{
    $ch = curl_init();

    $headers = [
        'Authorization: Bearer ' . $config['token'],
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $method = strtoupper($method);

    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data !== null ? json_encode($data) : '{}');
    } elseif ($method === 'PATCH') {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data !== null ? json_encode($data) : '{}');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $body    = (string)curl_exec($ch);
    $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    return [
        'code'  => $code,
        'body'  => $body,
        'data'  => json_decode($body, true),
        'error' => $curlErr,
    ];
}

/**
 * Load key=value pairs from an env file into the process environment.
 * Does not override variables already set in the environment.
 */
function loadEnvFile(string $path): void
{
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $eqPos = strpos($trimmed, '=');
        if ($eqPos === false) {
            continue;
        }

        $key   = trim(substr($trimmed, 0, $eqPos));
        $value = trim(substr($trimmed, $eqPos + 1));

        // Strip surrounding single or double quotes
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Honour shell inline comments (value # comment), naively strip after ' #'
        if (preg_match('/^(.*?)\s+#/', $value, $m)) {
            $value = $m[1];
        }

        // Don't override environment variables already set
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Return an env variable or die with a clear message.
 */
function requireEnv(string $key): string
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        fatal("Required environment variable {$key} is not set.\nSee scripts/agent-runner.example.env for configuration.");
    }
    return $val;
}

/**
 * Return an env variable as a boolean (true/false/1/0/yes/no).
 */
function getEnvBool(string $key): bool
{
    $val = strtolower(trim((string)getenv($key)));
    return in_array($val, ['true', '1', 'yes', 'on'], true);
}

/**
 * Strip ANSI escape sequences from a string.
 */
function stripAnsi(string $text): string
{
    return (string)preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $text);
}

/**
 * Emit a timestamped log line to stdout.
 */
function log_msg(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$message}\n";
}

/**
 * Print an error and exit non-zero.
 */
function fatal(string $message): never
{
    $ts = date('Y-m-d H:i:s');
    fwrite(STDERR, "[{$ts}] FATAL: {$message}\n");
    exit(1);
}
