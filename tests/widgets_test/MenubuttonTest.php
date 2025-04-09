<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Menubutton;

$app = new Application();
$window = new Window(['title' => 'Menubutton Test']);

// Create a Menubutton widget with custom text.
$menubutton = new Menubutton($window->getId(), ['text' => 'Test Menubutton']);
echo "MenubuttonTest: Menubutton widget created with text: 'Test Menubutton'\n";

// Destroy the widget.
$menubutton->destroy();
echo "MenubuttonTest: Menubutton widget destroyed\n";

$app->quit();
