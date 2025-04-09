<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Menu;

$app = new Application();
$window = new Window(['title' => 'Menu Test']);

// Create a Menu widget inside the window.
$menu = new Menu($window->getId(), []);
echo "MenuTest: Menu widget created\n";

// Destroy the widget.
$menu->destroy();
echo "MenuTest: Menu widget destroyed\n";

$app->quit();
