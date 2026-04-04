<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();

$webview = new WebView([
    'title' => 'PhpGui WebView Test - Todo App',
    'width' => 900,
    'height' => 700,
    'debug' => true,
]);
$app->addWebView($webview);

// Data 
$todos = [
    ['id' => '1', 'text' => 'php task 1', 'completed' => true],
    ['id' => '2', 'text' => 'Build a nice GUI with web tech', 'completed' => false],
];

$webview->initJs('
    console.log("initJs: This runs before anything else.");
    window.AppConfig = { version: "1.0.0" };
');

$html = file_get_contents(__DIR__ . '/index.html');
$webview->setHtml($html);

// Lifecycle Hooks
$webview->onReady(function() {
    echo "WebView Ready\n";
});

$webview->onClose(function() use ($app) {
    echo "WebView Closed\n";
    $app->quit();
});

$webview->onError(function() {
    echo "WebView Error\n";
});

// (JS -> PHP)
$webview->bind('getTodos', function(string $reqId, string $args) use ($webview, &$todos) {
    $webview->returnValue($reqId, 0, json_encode($todos));
});

$webview->bind('addTodo', function(string $reqId, string $args) use ($webview, &$todos) {
    $data = json_decode($args, true);
    $text = $data[0] ?? '';
    if (trim($text) !== '') {
        $todos[] = [
            'id' => uniqid(),
            'text' => $text,
            'completed' => false,
        ];
        // Emit event (PHP -> JS)
        $webview->emit('todosUpdated', $todos);
    }
    $webview->returnValue($reqId, 0, json_encode(true));
});

$webview->bind('toggleTodo', function(string $reqId, string $args) use ($webview, &$todos) {
    $data = json_decode($args, true);
    $id = $data[0] ?? '';
    foreach ($todos as &$todo) {
        if ($todo['id'] == $id) {
            $todo['completed'] = !$todo['completed'];
            break;
        }
    }
    $webview->emit('todosUpdated', $todos);
    $webview->returnValue($reqId, 0, json_encode(true));
});

$webview->bind('deleteTodo', function(string $reqId, string $args) use ($webview, &$todos) {
    $data = json_decode($args, true);
    $id = $data[0] ?? '';
    $todos = array_filter($todos, fn($todo) => $todo['id'] != $id);
    $todos = array_values($todos);
    $webview->emit('todosUpdated', $todos);
    $webview->returnValue($reqId, 0, json_encode(true));
});

$webview->bind('setTitle', function(string $reqId, string $args) use ($webview) {
    $data = json_decode($args, true);
    $title = $data[0] ?? 'PhpGui WebView Test';
    $webview->setTitle($title);
    $webview->returnValue($reqId, 0, json_encode(true));
});

$webview->bind('setSize', function(string $reqId, string $args) use ($webview) {
    $data = json_decode($args, true);
    $width = (int)($data[0] ?? 800);
    $height = (int)($data[1] ?? 600);
    $webview->setSize($width, $height);
    $webview->returnValue($reqId, 0, json_encode(true));
});

$webview->bind('evalAlert', function(string $reqId, string $args) use ($webview) {
    $webview->evalJs('alert("This is an alert triggered from PHP via evalJs!");');
    $webview->returnValue($reqId, 0, json_encode(true));
});

$app->run();
