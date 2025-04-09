<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;

$app = new Application();

// Create a separate window for pack geometry manager
$windowPack = new Window(['title' => 'Pack Geometry Test']);
$labelPack = new Label($windowPack->getId(), ['text' => 'Pack Test']);
$labelPack->pack(['pady' => 5]);
echo "GeometryTest: Label packed with pack manager\n";

// Create a separate window for place geometry manager
$windowPlace = new Window(['title' => 'Place Geometry Test']);
$labelPlace = new Label($windowPlace->getId(), ['text' => 'Place Test']);
$labelPlace->place(['x' => 50, 'y' => 100]);
echo "GeometryTest: Label placed with place manager\n";

// Create a separate window for grid geometry manager
$windowGrid = new Window(['title' => 'Grid Geometry Test']);
$labelGrid = new Label($windowGrid->getId(), ['text' => 'Grid Test']);
$labelGrid->grid(['row' => 1, 'column' => 0]);
echo "GeometryTest: Label gridded with grid manager\n";

$app->quit();
