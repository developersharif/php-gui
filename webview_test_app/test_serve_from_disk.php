<?php
/**
 * Test serveFromDisk() — serves frontend from disk via custom URI scheme.
 * No HTTP server, no port, no firewall prompts.
 *
 * Usage: php webview_test_app/test_serve_from_disk.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpGui\Widget\WebView;

$webview = new WebView([
    'title'  => 'serveFromDisk Test',
    'width'  => 900,
    'height' => 700,
    'debug'  => true,
]);

// Inject AppConfig for the InitJS test to verify
$webview->initJs('
    window.AppConfig = { version: "1.0.0" };
');

// Serve the current directory's index.html via native custom URI scheme
$webview->serveFromDisk(__DIR__);

$webview->onServeDirReady(function (string $url) {
    echo "[PHP] Serving from: {$url}\n";
});

$webview->onReady(function () {
    echo "[PHP] WebView ready\n";
});

$webview->onClose(function () {
    echo "[PHP] WebView closed\n";
});

$webview->onError(function (string $msg) {
    echo "[PHP] Error: {$msg}\n";
});

// Bind the commands the test app expects
$todos = [
    ['id' => '1', 'text' => 'Test serveFromDisk()', 'completed' => false],
    ['id' => '2', 'text' => 'No HTTP server needed!', 'completed' => true],
    ['id' => '3', 'text' => 'Custom URI scheme works', 'completed' => false],
];

$webview->bind('getTodos', function (string $id, string $args) use ($webview, &$todos) {
    $webview->returnValue($id, 0, json_encode($todos));
});

$webview->bind('addTodo', function (string $id, string $args) use ($webview, &$todos) {
    $parsed = json_decode($args, true);
    $text = $parsed[0] ?? '';
    if (trim($text) !== '') {
        $todos[] = ['id' => uniqid(), 'text' => $text, 'completed' => false];
        $webview->emit('todosUpdated', $todos);
    }
    $webview->returnValue($id, 0, json_encode(true));
});

$webview->bind('toggleTodo', function (string $id, string $args) use ($webview, &$todos) {
    $parsed = json_decode($args, true);
    $todoId = $parsed[0] ?? '';
    foreach ($todos as &$t) {
        if ($t['id'] === $todoId) {
            $t['completed'] = !$t['completed'];
            break;
        }
    }
    $webview->returnValue($id, 0, json_encode(true));
    $webview->emit('todosUpdated', $todos);
});

$webview->bind('deleteTodo', function (string $id, string $args) use ($webview, &$todos) {
    $parsed = json_decode($args, true);
    $todoId = $parsed[0] ?? '';
    $todos = array_values(array_filter($todos, fn($t) => $t['id'] !== $todoId));
    $webview->returnValue($id, 0, json_encode(true));
    $webview->emit('todosUpdated', $todos);
});

$webview->bind('setTitle', function (string $id, string $args) use ($webview) {
    $parsed = json_decode($args, true);
    $webview->setTitle($parsed[0] ?? 'WebView');
    $webview->returnValue($id, 0, json_encode(true));
});

$webview->bind('setSize', function (string $id, string $args) use ($webview) {
    $parsed = json_decode($args, true);
    $webview->setSize($parsed[0] ?? 800, $parsed[1] ?? 600);
    $webview->returnValue($id, 0, json_encode(true));
});

$webview->bind('evalAlert', function (string $id, string $args) use ($webview) {
    $webview->evalJs('alert("Hello from PHP via evalJs!")');
    $webview->returnValue($id, 0, json_encode(true));
});

echo "[PHP] Serving from disk (no HTTP server) — close window to exit\n";

// Simple event loop
while (!$webview->isClosed()) {
    $webview->processEvents();
    usleep(20000); // 20ms
}

echo "[PHP] Done\n";
