<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;

$app = new Application();
$window = new Window(['title' => 'Label Test']);
$label = new Label($window->getId(), ['text' => 'Test Label']);

echo "LabelTest: Label created with text: 'Test Label'\n";

// End test
$app->quit();
