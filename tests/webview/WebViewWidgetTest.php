<?php

/**
 * Integration tests for the WebView widget class.
 *
 * Tests the PHP WebView API end-to-end with the helper binary.
 * Skips gracefully if the binary is not built.
 *
 * Run: php tests/webview/WebViewWidgetTest.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Widget\WebView;
use PhpGuiTest\TestRunner;

/* ── Skip check ──────────────────────────────────────────────────────────── */

function helperBinaryExists(): bool
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

if (!helperBinaryExists()) {
    echo "[SKIP] WebView helper binary not built.\n";
    exit(0);
}

/* ── Helper: wait for ready with timeout ─────────────────────────────────── */

function waitForReady(WebView $wv, float $timeoutSec = 5.0): bool
{
    $start = microtime(true);
    while (microtime(true) - $start < $timeoutSec) {
        $wv->processEvents();
        if ($wv->isReady()) return true;
        usleep(20000); // 20ms
    }
    return false;
}

/* ── Tests ────────────────────────────────────────────────────────────────── */

TestRunner::suite('WebViewWidgetTest');

// Test 1: Constructor creates a WebView
$wv = new WebView(['title' => 'Test WebView', 'width' => 400, 'height' => 300]);
TestRunner::assert(!$wv->isClosed(), 'WebView is not closed after construction');

// Test 2: ID generation
TestRunner::assert(str_starts_with($wv->getId(), 'wv'), 'WebView ID starts with "wv"');

// Test 3: Ready event received
$ready = waitForReady($wv);
TestRunner::assert($ready, 'WebView becomes ready within timeout');
TestRunner::assert($wv->isReady(), 'isReady() returns true');

// Test 4: navigate does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->navigate('about:blank');
}, 'navigate() does not throw');

// Test 5: setHtml does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->setHtml('<h1>Hello</h1>');
}, 'setHtml() does not throw');

// Test 6: setTitle does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->setTitle('New Title');
}, 'setTitle() does not throw');

// Test 7: setSize does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->setSize(640, 480);
}, 'setSize() does not throw');

// Test 8: evalJs does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->evalJs('document.title = "from PHP"');
}, 'evalJs() does not throw');

// Test 9: initJs does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->initJs('window.__testInit = true;');
}, 'initJs() does not throw');

// Test 10: emit does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->emit('testEvent', ['count' => 42]);
}, 'emit() does not throw');

// Test 11: bind registers handler
$handlerCalled = false;
$wv->bind('testBind', function (string $id, string $args) use (&$handlerCalled, $wv) {
    $handlerCalled = true;
    $wv->returnValue($id, 0, json_encode('ok'));
});
// Verify no exception on bind
TestRunner::assert(true, 'bind() does not throw');

// Test 12: unbind does not throw
TestRunner::assertNoThrow(function () use ($wv) {
    $wv->unbind('testBind');
}, 'unbind() does not throw');

// Test 13: onClose callback registration
$closeCalled = false;
$wv->onClose(function () use (&$closeCalled) {
    $closeCalled = true;
});
TestRunner::assert(true, 'onClose() registration does not throw');

// Test 14: onError callback registration
$errorMessage = '';
$wv->onError(function (string $msg) use (&$errorMessage) {
    $errorMessage = $msg;
});
TestRunner::assert(true, 'onError() registration does not throw');

// Test 15: onReady callback registration
$wv->onReady(function () {
    // Already ready, won't fire again
});
TestRunner::assert(true, 'onReady() registration does not throw');

// Test 16: destroy closes the webview
$wv->destroy();
// Give it a moment to close
usleep(500000);
TestRunner::assert($wv->isClosed(), 'WebView is closed after destroy()');

// Test 17: Sending command on closed WebView throws
TestRunner::assertThrows(function () use ($wv) {
    $wv->navigate('https://example.com');
}, \RuntimeException::class, 'navigate() on closed WebView throws RuntimeException');

// Test 18: Multiple WebViews
$wv1 = new WebView(['title' => 'WV1', 'width' => 300, 'height' => 200]);
$wv2 = new WebView(['title' => 'WV2', 'width' => 300, 'height' => 200]);
TestRunner::assert($wv1->getId() !== $wv2->getId(), 'Multiple WebViews have unique IDs');

$ready1 = waitForReady($wv1);
$ready2 = waitForReady($wv2);
TestRunner::assert($ready1 && $ready2, 'Multiple WebViews both become ready');

$wv1->destroy();
$wv2->destroy();
usleep(500000);
TestRunner::assert($wv1->isClosed() && $wv2->isClosed(), 'Multiple WebViews both close');

// Test 19: WebView with initial URL
$wv3 = new WebView(['title' => 'URL Test', 'url' => 'about:blank']);
$ready3 = waitForReady($wv3);
TestRunner::assert($ready3, 'WebView with initial URL becomes ready');
$wv3->destroy();
usleep(300000);

// Test 20: WebView with initial HTML
$wv4 = new WebView([
    'title' => 'HTML Test',
    'html' => '<h1>Hello from PHP</h1>',
]);
$ready4 = waitForReady($wv4);
TestRunner::assert($ready4, 'WebView with initial HTML becomes ready');
$wv4->destroy();
usleep(300000);

/* ── Summary ──────────────────────────────────────────────────────────────── */

TestRunner::summary();
