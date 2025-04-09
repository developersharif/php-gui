<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Input;

$app = new Application();
$window = new Window(['title' => 'Input Test']);
$input = new Input($window->getId(), ['text' => 'Initial Text']);

echo "InputTest: Input created with initial text: " . $input->getValue() . "\n";

$input->setValue("New Text");
echo "InputTest: Input updated text: " . $input->getValue() . "\n";


$app->quit();
