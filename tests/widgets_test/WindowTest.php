<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;

$app = new Application();
$window = new Window([
    'title' => 'Window Test',
    'width' => 400,
    'height' => 300
]);

echo "WindowTest: Window created with id " . $window->getId() . "\n";

// End test
$app->quit();
