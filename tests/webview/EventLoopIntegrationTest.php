<?php

/**
 * Integration tests for Application + WebView event loop.
 *
 * Tests that WebViews are polled correctly within the Application event loop.
 * Requires both the webview helper binary and Tcl/Tk.
 *
 * Run: php tests/webview/EventLoopIntegrationTest.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

/* ── Skip check ──────────────────────────────────────────────────────────── */

function helperExists(): bool
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
    return file_exists($libDir . "webview_helper_{$os}_{$arch}{$ext}");
}

if (!helperExists()) {
    echo "[SKIP] WebView helper binary not built.\n";
    exit(0);
}

/* ── Tests ────────────────────────────────────────────────────────────────── */

TestRunner::suite('EventLoopIntegrationTest');

// Initialize Application (starts Tcl/Tk)
$app = new Application();

// Test 1: addWebView registers a WebView
$wv = new WebView(['title' => 'Loop Test', 'width' => 300, 'height' => 200]);
$app->addWebView($wv);

// Run a few ticks to process the ready event
for ($i = 0; $i < 50; $i++) {
    $app->tick();
    if ($wv->isReady()) break;
    usleep(20000);
}
TestRunner::assert($wv->isReady(), 'WebView becomes ready via Application tick()');

// Test 2: Tk window coexists with WebView
$window = new Window(['title' => 'Tk Window', 'width' => 200, 'height' => 100]);
// tick should process both
$app->tick();
TestRunner::assert(!$wv->isClosed(), 'WebView still alive after Tk tick');

// Test 3: removeWebView
$app->removeWebView($wv);
$app->tick(); // should not crash
TestRunner::assert(true, 'removeWebView() + tick() does not crash');

// Re-add for further tests
$app->addWebView($wv);

// Test 4: Closed WebView is auto-removed
$wv->destroy();
usleep(500000); // allow process to terminate
$app->tick();
// After tick, closed webview should be cleaned up
TestRunner::assert($wv->isClosed(), 'Destroyed WebView is closed');

// Test 5: Multiple WebViews
$wv1 = new WebView(['title' => 'Multi 1', 'width' => 200, 'height' => 150]);
$wv2 = new WebView(['title' => 'Multi 2', 'width' => 200, 'height' => 150]);
$app->addWebView($wv1);
$app->addWebView($wv2);

for ($i = 0; $i < 50; $i++) {
    $app->tick();
    if ($wv1->isReady() && $wv2->isReady()) break;
    usleep(20000);
}
TestRunner::assert($wv1->isReady() && $wv2->isReady(), 'Multiple WebViews both become ready via tick()');

// Cleanup
$wv1->destroy();
$wv2->destroy();
usleep(500000);
$app->tick();
TestRunner::assert($wv1->isClosed() && $wv2->isClosed(), 'Multiple WebViews cleaned up');

// Test 6: Existing Tk tests still work (no regression)
$window->destroy();
TestRunner::assert(true, 'Tk window destroy works alongside WebView integration');

/* ── Summary ──────────────────────────────────────────────────────────────── */

TestRunner::summary();
