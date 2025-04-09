<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Canvas;
use PhpGui\Widget\Checkbutton;
use PhpGui\Widget\Combobox;
use PhpGui\Widget\Entry;
use PhpGui\Widget\Frame;
use PhpGui\Widget\Window;

$app = new Application();
$window = new Window(['title' => 'Additional Widgets Test']);

// Test Canvas widget
$canvas = new Canvas($window->getId(), []);
echo "AdditionalWidgetsTest: Canvas widget created\n";
$canvas->destroy();

// Test Checkbutton widget
$check = new Checkbutton($window->getId(), ['text' => 'Test Checkbutton']);
echo "AdditionalWidgetsTest: Checkbutton widget created\n";
$check->destroy();

// Test Combobox widget
$combo = new Combobox($window->getId(), ['values' => 'Option1 Option2 Option3']);
echo "AdditionalWidgetsTest: Combobox widget created\n";
$combo->destroy();

// Test Entry widget
$entry = new Entry($window->getId(), ['text' => 'Default Entry']);
echo "AdditionalWidgetsTest: Entry widget created\n";
$entry->destroy();

// Test Frame widget
$frame = new Frame($window->getId(), []);
echo "AdditionalWidgetsTest: Frame widget created\n";
$frame->destroy();

$app->quit();
