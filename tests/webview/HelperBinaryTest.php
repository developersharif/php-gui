<?php

/**
 * Integration tests for the webview helper binary.
 *
 * Tests the C helper binary in isolation via proc_open — no Tcl/Tk required.
 * Skips gracefully if the binary is not built.
 *
 * Run: php tests/webview/HelperBinaryTest.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGuiTest\TestRunner;

/* ── Helper: locate the binary ──────────────────────────────────────────── */

function findHelperBinary(): ?string
{
    $libDir = __DIR__ . '/../../src/lib/';
    $os = match (PHP_OS_FAMILY) {
        'Darwin'  => 'darwin',
        'Windows' => 'windows',
        default   => 'linux',
    };
    $arch = php_uname('m');
    if ($os === 'darwin' && $arch === 'aarch64') $arch = 'arm64';
    $ext = $os === 'windows' ? '.exe' : '';
    $path = $libDir . "webview_helper_{$os}_{$arch}{$ext}";
    return file_exists($path) ? $path : null;
}

/* ── Helper: launch the binary ──────────────────────────────────────────── */

function launchHelper(string $binary, array $args = []): array
{
    $cmd = escapeshellarg($binary);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }

    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException("Failed to launch helper binary");
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return ['process' => $process, 'stdin' => $pipes[0], 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
}

/* ── Helper: read a JSON event with timeout ──────────────────────────────── */

function readEvent(array &$helper, float $timeoutSec = 5.0): ?array
{
    $start = microtime(true);
    $buffer = '';

    while (microtime(true) - $start < $timeoutSec) {
        $chunk = @fread($helper['stdout'], 8192);
        if ($chunk !== false && $chunk !== '') {
            $buffer .= $chunk;
            if (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $event = json_decode(trim($line), true);
                if (is_array($event)) return $event;
            }
        }
        usleep(10000); // 10ms
    }

    return null;
}

/* ── Helper: send a command ──────────────────────────────────────────────── */

function sendCommand(array &$helper, array $cmd): void
{
    $cmd['version'] = 1;
    $json = json_encode($cmd, JSON_UNESCAPED_SLASHES) . "\n";
    fwrite($helper['stdin'], $json);
    fflush($helper['stdin']);
}

/* ── Helper: cleanup ─────────────────────────────────────────────────────── */

function cleanupHelper(array &$helper): void
{
    if (is_resource($helper['stdin'] ?? null))  @fclose($helper['stdin']);
    if (is_resource($helper['stdout'] ?? null)) @fclose($helper['stdout']);
    if (is_resource($helper['stderr'] ?? null)) @fclose($helper['stderr']);
    if (is_resource($helper['process'])) {
        $status = proc_get_status($helper['process']);
        if ($status['running']) {
            proc_terminate($helper['process']);
        }
        proc_close($helper['process']);
    }
}

/* ── Skip check ──────────────────────────────────────────────────────────── */

$binary = findHelperBinary();
if ($binary === null) {
    echo "[SKIP] WebView helper binary not built.\n";
    echo "       Build it: cd src/lib/webview_helper && bash build.sh\n";
    echo "       Linux requires: sudo apt-get install -y libgtk-3-dev libwebkit2gtk-4.1-dev\n";
    exit(0);
}

/* ── Tests ────────────────────────────────────────────────────────────────── */

TestRunner::suite('HelperBinaryTest');

// Test 1: Binary launches and sends ready event
$helper = launchHelper($binary, ['--title', 'Test Window', '--width', '400', '--height', '300']);
$event = readEvent($helper, 5.0);
TestRunner::assert($event !== null, 'Helper binary launches and produces output');
TestRunner::assertEqual('ready', $event['event'] ?? '', 'First event is "ready"');
TestRunner::assertEqual(1, $event['version'] ?? 0, 'Event includes version: 1');

// Test 2: Ping/pong
sendCommand($helper, ['cmd' => 'ping']);
$event = readEvent($helper, 3.0);
TestRunner::assertEqual('pong', $event['event'] ?? '', 'Ping returns pong');
TestRunner::assertEqual(1, $event['version'] ?? 0, 'Pong includes version: 1');

// Test 3: Destroy command terminates cleanly
sendCommand($helper, ['cmd' => 'destroy']);
$event = readEvent($helper, 10.0);
TestRunner::assertEqual('closed', $event['event'] ?? '', 'Destroy triggers closed event');

// Wait for process to exit
$exitTimeout = microtime(true) + 5.0;
$exited = false;
while (microtime(true) < $exitTimeout) {
    $status = proc_get_status($helper['process']);
    if (!$status['running']) {
        $exited = true;
        break;
    }
    usleep(100000); // 100ms
}
TestRunner::assert($exited, 'Process exits after destroy command');
cleanupHelper($helper);

// Test 4: Stdin EOF causes termination
$helper2 = launchHelper($binary, ['--title', 'EOF Test']);
$event = readEvent($helper2, 5.0);
TestRunner::assertEqual('ready', $event['event'] ?? '', 'Second instance sends ready');

fclose($helper2['stdin']);
$helper2['stdin'] = null; // prevent double close

$event = readEvent($helper2, 5.0);
TestRunner::assertEqual('closed', $event['event'] ?? '', 'Stdin EOF triggers closed event');

$exitTimeout = microtime(true) + 3.0;
$exited = false;
while (microtime(true) < $exitTimeout) {
    $status = proc_get_status($helper2['process']);
    if (!$status['running']) {
        $exited = true;
        break;
    }
    usleep(50000);
}
TestRunner::assert($exited, 'Process exits after stdin EOF');
cleanupHelper($helper2);

// Test 5: Navigate command (no crash)
$helper3 = launchHelper($binary, ['--title', 'Navigate Test']);
$event = readEvent($helper3, 5.0);
TestRunner::assertEqual('ready', $event['event'] ?? '', 'Third instance sends ready');

sendCommand($helper3, ['cmd' => 'navigate', 'url' => 'about:blank']);
// Give it a moment — no error expected
usleep(200000);

// Ping to verify helper is still alive
sendCommand($helper3, ['cmd' => 'ping']);
$event = readEvent($helper3, 3.0);
TestRunner::assertEqual('pong', $event['event'] ?? '', 'Helper still alive after navigate');

// Test 6: set_html command (no crash)
sendCommand($helper3, ['cmd' => 'set_html', 'html' => '<h1>Test</h1>']);
usleep(200000);
sendCommand($helper3, ['cmd' => 'ping']);
$event = readEvent($helper3, 3.0);
TestRunner::assertEqual('pong', $event['event'] ?? '', 'Helper still alive after set_html');

// Test 7: set_title command
sendCommand($helper3, ['cmd' => 'set_title', 'title' => 'New Title']);
usleep(100000);
sendCommand($helper3, ['cmd' => 'ping']);
$event = readEvent($helper3, 3.0);
TestRunner::assertEqual('pong', $event['event'] ?? '', 'Helper still alive after set_title');

// Test 8: Unknown command returns error
sendCommand($helper3, ['cmd' => 'nonexistent_command']);
$event = readEvent($helper3, 3.0);
TestRunner::assertEqual('error', $event['event'] ?? '', 'Unknown command returns error event');
TestRunner::assert(
    str_contains($event['message'] ?? '', 'Unknown command'),
    'Error message mentions unknown command'
);

// Cleanup
sendCommand($helper3, ['cmd' => 'destroy']);
readEvent($helper3, 3.0); // consume closed event
cleanupHelper($helper3);

/* ── Summary ──────────────────────────────────────────────────────────────── */

TestRunner::summary();
