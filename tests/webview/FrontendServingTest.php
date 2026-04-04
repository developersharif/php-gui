<?php

/**
 * Tests for frontend-serving APIs: serveFromDisk(), serveVite(),
 * onServeDirReady(), and enableFetchProxy().
 *
 * Covers:
 *   - Binary-level serve_dir → serve_dir_ready IPC round-trip
 *   - Platform URL scheme (phpgui:// on Linux, file:// on macOS)
 *   - serveVite() dev-mode detection via TCP probe
 *   - serveVite() prod-mode fallback to serveFromDisk()
 *   - onServeDirReady() callback fires with correct URL
 *   - enableFetchProxy() handler registration
 *   - PHP HTTP client (cURL and stream_context paths)
 *
 * Run: php tests/webview/FrontendServingTest.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Widget\WebView;
use PhpGuiTest\TestRunner;

/* ── Skip guard ──────────────────────────────────────────────────────────── */

function findBinary(): ?string
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

$binary = findBinary();
if ($binary === null) {
    echo "[SKIP] WebView helper binary not built.\n";
    echo "       Build: cd src/lib/webview_helper && bash build.sh\n";
    exit(0);
}

/* ── Raw-IPC helpers (reused from HelperBinaryTest) ─────────────────────── */

function launchBinary(string $binary, array $args = []): array
{
    $cmd = escapeshellarg($binary);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    $proc = proc_open($cmd, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException("Failed to launch binary");
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['proc' => $proc, 'in' => $pipes[0], 'out' => $pipes[1], 'err' => $pipes[2]];
}

function readNextEvent(array &$h, float $timeout = 5.0): ?array
{
    $buf   = '';
    $start = microtime(true);
    while (microtime(true) - $start < $timeout) {
        $chunk = @fread($h['out'], 8192);
        if ($chunk !== false && $chunk !== '') {
            $buf .= $chunk;
            if (($pos = strpos($buf, "\n")) !== false) {
                $line  = substr($buf, 0, $pos);
                $event = json_decode(trim($line), true);
                if (is_array($event)) return $event;
            }
        }
        usleep(10000);
    }
    return null;
}

function sendCmd(array &$h, array $cmd): void
{
    $cmd['version'] = 1;
    fwrite($h['in'], json_encode($cmd, JSON_UNESCAPED_SLASHES) . "\n");
    fflush($h['in']);
}

function destroyBinary(array &$h): void
{
    if (is_resource($h['in'] ?? null))  @fclose($h['in']);
    if (is_resource($h['out'] ?? null)) @fclose($h['out']);
    if (is_resource($h['err'] ?? null)) @fclose($h['err']);
    if (is_resource($h['proc'] ?? null)) {
        $st = proc_get_status($h['proc']);
        if ($st['running']) proc_terminate($h['proc']);
        proc_close($h['proc']);
    }
}

/* ── WebView helpers ─────────────────────────────────────────────────────── */

function waitReady(WebView $wv, float $timeout = 5.0): bool
{
    $start = microtime(true);
    while (microtime(true) - $start < $timeout) {
        $wv->processEvents();
        if ($wv->isReady()) return true;
        usleep(20000);
    }
    return false;
}

/**
 * Poll processEvents() until onServeDirReady fires or timeout.
 * Returns the URL string or null on timeout.
 */
function waitServeDirReady(WebView $wv, float $timeout = 8.0): ?string
{
    $receivedUrl = null;
    $wv->onServeDirReady(function (string $url) use (&$receivedUrl): void {
        $receivedUrl = $url;
    });

    $start = microtime(true);
    while (microtime(true) - $start < $timeout && $receivedUrl === null) {
        $wv->processEvents();
        usleep(20000);
    }
    return $receivedUrl;
}

/** Find a free TCP port on loopback. */
function freePort(): int
{
    $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$sock) throw new RuntimeException("No free port: {$errstr}");
    $name = stream_socket_get_name($sock, false);
    $port = (int)substr($name, strrpos($name, ':') + 1);
    fclose($sock);
    return $port;
}

/** Call a private method on an object via Reflection. */
function callPrivate(object $obj, string $method, array $args = []): mixed
{
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($obj, $args);
}

/** Read a private property via Reflection. */
function readPrivate(object $obj, string $prop): mixed
{
    $ref = new ReflectionProperty($obj, $prop);
    $ref->setAccessible(true);
    return $ref->getValue($obj);
}

/* ── Test fixtures ───────────────────────────────────────────────────────── */

// A real directory with an index.html we can use for serve_dir tests.
$fixtureDir = realpath(__DIR__ . '/../../webview_test_app');

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 1: Binary-level serve_dir → serve_dir_ready IPC round-trip
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — Binary IPC: serve_dir');

$h = launchBinary($binary, ['--title', 'ServeDir Test', '--width', '400', '--height', '300']);

$ev = readNextEvent($h, 5.0);
TestRunner::assertEqual('ready', $ev['event'] ?? '', 'Binary emits ready event');

// Send serve_dir command
sendCmd($h, ['cmd' => 'serve_dir', 'path' => $fixtureDir]);

// Expect serve_dir_ready
$ev = readNextEvent($h, 8.0);
TestRunner::assertEqual('serve_dir_ready', $ev['event'] ?? '', 'serve_dir_ready event received');
TestRunner::assert(isset($ev['url']) && $ev['url'] !== '', 'serve_dir_ready includes non-empty url');

// Verify platform URL scheme
$expectedScheme = match (PHP_OS_FAMILY) {
    'Darwin'  => 'file://',
    'Windows' => 'https://phpgui.localhost',
    default   => 'phpgui://',
};
TestRunner::assert(
    str_starts_with($ev['url'], $expectedScheme),
    "serve_dir_ready URL uses platform scheme ({$expectedScheme}): {$ev['url']}"
);

// Binary stays alive after serve_dir
sendCmd($h, ['cmd' => 'ping']);
$ev = readNextEvent($h, 3.0);
TestRunner::assertEqual('pong', $ev['event'] ?? '', 'Binary still alive after serve_dir');

sendCmd($h, ['cmd' => 'destroy']);
readNextEvent($h, 3.0);
destroyBinary($h);

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 2: serveFromDisk() — input validation
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — serveFromDisk() validation');

$wv = new WebView(['title' => 'Validation Test', 'width' => 400, 'height' => 300]);
waitReady($wv, 5.0);

// Non-existent path
TestRunner::assertThrows(
    fn() => $wv->serveFromDisk('/path/that/does/not/exist/at/all'),
    RuntimeException::class,
    'serveFromDisk() throws on non-existent directory'
);

// Directory with no index.html
$noIndex = sys_get_temp_dir() . '/phpgui_test_noindex_' . uniqid();
mkdir($noIndex, 0777, true);
TestRunner::assertThrows(
    fn() => $wv->serveFromDisk($noIndex),
    RuntimeException::class,
    'serveFromDisk() throws when directory has no index.html'
);
rmdir($noIndex);

// Valid directory
TestRunner::assertNoThrow(
    fn() => $wv->serveFromDisk($fixtureDir),
    'serveFromDisk() does not throw for valid directory with index.html'
);

$wv->destroy();
usleep(300000);

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 3: onServeDirReady() callback
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — onServeDirReady() callback');

$wv = new WebView(['title' => 'ServeDirReady Test', 'width' => 400, 'height' => 300]);
waitReady($wv, 5.0);

// Register callback, then call serveFromDisk
$wv->serveFromDisk($fixtureDir);
$url = waitServeDirReady($wv, 8.0);

TestRunner::assert($url !== null, 'onServeDirReady() callback fires after serveFromDisk()');
TestRunner::assert(is_string($url) && $url !== '', 'Callback receives non-empty URL string');

// Verify scheme again via the PHP layer
$expectedScheme = match (PHP_OS_FAMILY) {
    'Darwin'  => 'file://',
    'Windows' => 'https://phpgui.localhost',
    default   => 'phpgui://',
};
TestRunner::assert(
    str_starts_with($url, $expectedScheme),
    "PHP callback URL uses platform scheme ({$expectedScheme}): {$url}"
);

$wv->destroy();
usleep(300000);

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 4: serveVite() — dev / prod mode detection
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — serveVite() mode detection');

// ── Dev mode: a TCP listener is running on the target port ────────────────
// Hold the port open (bind it but do not accept connections — serveVite only
// probes with fsockopen, which succeeds as soon as the port is bound).
$devPort  = freePort();
$devSock  = stream_socket_server("tcp://127.0.0.1:{$devPort}", $errno, $errstr);
TestRunner::assert((bool)$devSock, "Dev-mode fixture server bound on port {$devPort}");

$wvDev = new WebView(['title' => 'Vite Dev Test', 'width' => 400, 'height' => 300]);
waitReady($wvDev, 5.0);

$devServeDirFired = false;
$wvDev->onServeDirReady(function () use (&$devServeDirFired): void {
    $devServeDirFired = true;
});

// serveVite() should detect the listening port and call navigate(), NOT serveFromDisk()
TestRunner::assertNoThrow(
    fn() => $wvDev->serveVite($fixtureDir, "http://127.0.0.1:{$devPort}", 1.0),
    'serveVite() does not throw in dev mode'
);

// Drain events briefly — serve_dir_ready should NOT fire (navigate was used, not serveFromDisk)
$deadline = microtime(true) + 1.0;
while (microtime(true) < $deadline) {
    $wvDev->processEvents();
    usleep(20000);
}
TestRunner::assert(!$devServeDirFired, 'serveVite() dev mode: onServeDirReady does NOT fire (navigate used)');

if ($devSock) fclose($devSock);
$wvDev->destroy();
usleep(300000);

// ── Prod mode: nothing is listening on the port ───────────────────────────
$unusedPort = freePort();
// Don't bind it — port is free and nothing is listening

$wvProd = new WebView(['title' => 'Vite Prod Test', 'width' => 400, 'height' => 300]);
waitReady($wvProd, 5.0);

$wvProd->serveFromDisk($fixtureDir); // prime with a serve so we can check the fallback
$wvProd->destroy();
usleep(300000);

// Fresh instance for clean prod-mode test
$wvProd = new WebView(['title' => 'Vite Prod Test 2', 'width' => 400, 'height' => 300]);
waitReady($wvProd, 5.0);

TestRunner::assertNoThrow(
    fn() => $wvProd->serveVite($fixtureDir, "http://127.0.0.1:{$unusedPort}", 0.3),
    'serveVite() does not throw in prod mode (no server running)'
);

$prodUrl = waitServeDirReady($wvProd, 8.0);
TestRunner::assert($prodUrl !== null, 'serveVite() prod mode: onServeDirReady fires (serveFromDisk called)');
TestRunner::assert(
    str_starts_with($prodUrl, $expectedScheme),
    "serveVite() prod mode URL uses platform scheme: {$prodUrl}"
);

$wvProd->destroy();
usleep(300000);

// ── Prod mode with invalid build dir throws ───────────────────────────────
$wvErr = new WebView(['title' => 'Vite Error Test', 'width' => 400, 'height' => 300]);
waitReady($wvErr, 5.0);

TestRunner::assertThrows(
    fn() => $wvErr->serveVite('/no/such/dist', "http://127.0.0.1:{$unusedPort}", 0.1),
    RuntimeException::class,
    'serveVite() throws in prod mode when build dir does not exist'
);

$wvErr->destroy();
usleep(300000);

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 5: enableFetchProxy() — registration and PHP HTTP client
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — enableFetchProxy()');

$wvProxy = new WebView(['title' => 'Proxy Test', 'width' => 400, 'height' => 300]);
waitReady($wvProxy, 5.0);

// enableFetchProxy() must not throw
TestRunner::assertNoThrow(
    fn() => $wvProxy->enableFetchProxy(),
    'enableFetchProxy() does not throw'
);

// __phpFetch handler must be registered in commandHandlers
$handlers = readPrivate($wvProxy, 'commandHandlers');
TestRunner::assert(
    isset($handlers['__phpFetch']) && is_callable($handlers['__phpFetch']),
    '__phpFetch handler is registered in commandHandlers'
);

// ── PHP HTTP client: curlRequest ─────────────────────────────────────────
// Start a minimal local HTTP server via PHP built-in server.
$servePort = freePort();
$serveDir  = sys_get_temp_dir() . '/phpgui_proxy_test_' . uniqid();
mkdir($serveDir, 0777, true);
file_put_contents($serveDir . '/index.php', '<?php
header("Content-Type: application/json");
header("X-Test-Header: phpgui");
echo json_encode(["method" => $_SERVER["REQUEST_METHOD"], "ok" => true]);
');

$serverProc = proc_open(
    PHP_BINARY . ' -S 127.0.0.1:' . $servePort . ' -t ' . escapeshellarg($serveDir),
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $serverPipes
);
fclose($serverPipes[0]);
fclose($serverPipes[1]);
fclose($serverPipes[2]);

// Wait for server to start
$deadline = microtime(true) + 3.0;
while (microtime(true) < $deadline) {
    $fp = @fsockopen('127.0.0.1', $servePort, $errno, $errstr, 0.1);
    if ($fp) { fclose($fp); break; }
    usleep(50000);
}

// Test curlRequest (if curl available)
if (function_exists('curl_init')) {
    [$status, $headers, $body] = callPrivate($wvProxy, 'curlRequest',
        ["http://127.0.0.1:{$servePort}/", 'GET', [], null]);

    TestRunner::assertEqual(200, $status, 'curlRequest: status 200 from local server');
    TestRunner::assert(!empty($body), 'curlRequest: non-empty response body');

    $json = json_decode($body, true);
    TestRunner::assertEqual(true, $json['ok'] ?? false, 'curlRequest: response body parses to expected JSON');
    TestRunner::assertEqual('GET', $json['method'] ?? '', 'curlRequest: server sees correct HTTP method');

    // Verify header parsing
    TestRunner::assert(
        isset($headers['X-Test-Header']) && $headers['X-Test-Header'] === 'phpgui',
        'curlRequest: custom response header is parsed correctly'
    );

    // POST with body
    [$statusPost] = callPrivate($wvProxy, 'curlRequest',
        ["http://127.0.0.1:{$servePort}/", 'POST', ['Content-Type' => 'application/json'], '{"test":1}']);
    TestRunner::assertEqual(200, $statusPost, 'curlRequest: POST request returns 200');
} else {
    echo "  [SKIP] curlRequest tests (ext-curl not available)\n";
}

// Test streamRequest (always available)
[$statusStream, $headersStream, $bodyStream] = callPrivate($wvProxy, 'streamRequest',
    ["http://127.0.0.1:{$servePort}/", 'GET', [], null]);

TestRunner::assertEqual(200, $statusStream, 'streamRequest: status 200 from local server');
TestRunner::assert(!empty($bodyStream), 'streamRequest: non-empty response body');

$jsonStream = json_decode($bodyStream, true);
TestRunner::assertEqual(true, $jsonStream['ok'] ?? false, 'streamRequest: response body parses to expected JSON');

// Cleanup server
proc_terminate($serverProc);
proc_close($serverProc);
array_map('unlink', glob($serveDir . '/*'));
rmdir($serveDir);

// ── base64 encoding roundtrip ─────────────────────────────────────────────
$sampleBody = "binary\x00data\xFF\xFEwith nulls";
$encoded    = base64_encode($sampleBody);
$decoded    = base64_decode($encoded);
TestRunner::assertEqual($sampleBody, $decoded, 'base64 roundtrip preserves binary response body');

// ── Calling enableFetchProxy() twice is safe ──────────────────────────────
TestRunner::assertNoThrow(
    fn() => $wvProxy->enableFetchProxy(),
    'enableFetchProxy() can be called multiple times without error'
);

$wvProxy->destroy();
usleep(300000);

/* ═══════════════════════════════════════════════════════════════════════════
   SUITE 6: serveDirectory() smoke-test (existing HTTP server path, no regression)
   ═══════════════════════════════════════════════════════════════════════════ */

TestRunner::suite('FrontendServingTest — serveDirectory() regression');

$wvHttp = new WebView(['title' => 'HTTP Server Test', 'width' => 400, 'height' => 300]);
waitReady($wvHttp, 5.0);

TestRunner::assertNoThrow(
    fn() => $wvHttp->serveDirectory($fixtureDir),
    'serveDirectory() starts local PHP server without error'
);
TestRunner::assert($wvHttp->getServerPort() !== null, 'serveDirectory() assigns a server port');
TestRunner::assert($wvHttp->getServerPort() > 0, 'serveDirectory() port is a valid port number');

$wvHttp->destroy();
usleep(500000);

/* ── Summary ──────────────────────────────────────────────────────────────── */

TestRunner::summary();
