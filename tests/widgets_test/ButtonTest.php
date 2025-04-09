<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Button;

$app = new Application();
$window = new Window(['title' => 'Button Test']);
$button = new Button($window->getId(), [
    'text' => 'Test Button',
    'command' => function() {
        echo "Button callback executed in ButtonTest.\n";
    }
]);

echo "ButtonTest: Button created with text: 'Test Button'\n";

$app->quit();
